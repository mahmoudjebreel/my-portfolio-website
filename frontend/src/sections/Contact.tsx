import { useEffect, useId, useRef, useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'
import { sendContactMessage } from '../api/endpoints'
import { Reveal } from '../components/ui/Reveal'
import { SectionHeading } from '../components/ui/SectionHeading'
import { useI18n } from '../i18n/I18nProvider'

type Field = 'name' | 'email' | 'subject' | 'message'
type Values = Record<Field, string>
type Status = 'idle' | 'sending' | 'sent' | 'failed' | 'rateLimited' | 'invalid'

const EMPTY: Values = { name: '', email: '', subject: '', message: '' }
const FIELDS: Field[] = ['name', 'email', 'subject', 'message']

// Same limits as the server-side rules in the approved design.
const LIMITS: Record<Field, { min: number; max: number }> = {
  name: { min: 2, max: 100 },
  email: { min: 3, max: 254 },
  subject: { min: 3, max: 150 },
  message: { min: 10, max: 5000 },
}
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export function Contact() {
  const { t, locale } = useI18n()
  const formId = useId()
  const [values, setValues] = useState<Values>(EMPTY)
  const [touched, setTouched] = useState<Partial<Record<Field, boolean>>>({})
  const [status, setStatus] = useState<Status>('idle')
  // Messages the server returned for fields (shown until that field is edited).
  const [serverErrors, setServerErrors] = useState<Partial<Record<Field, string>>>({})
  const request = useRef<AbortController | null>(null)

  useEffect(() => () => request.current?.abort(), [])

  const errorFor = (field: Field): string | null => {
    if (serverErrors[field]) return serverErrors[field]
    const value = values[field].trim()
    if (!value) return t.contact.required
    if (field === 'email' && !EMAIL.test(value)) return t.contact.invalidEmail
    if (value.length < LIMITS[field].min) return t.contact.tooShort(LIMITS[field].min)
    return null
  }

  const focusField = (field: Field) => document.getElementById(`${formId}-${field}`)?.focus()

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault()
    if (status === 'sending') return
    setTouched({ name: true, email: true, subject: true, message: true })
    const firstInvalid = FIELDS.find((field) => errorFor(field))
    if (firstInvalid) {
      focusField(firstInvalid)
      setStatus('idle')
      return
    }

    request.current?.abort()
    const controller = new AbortController()
    request.current = controller
    setStatus('sending')
    try {
      const trimmed = Object.fromEntries(FIELDS.map((field) => [field, values[field].trim()])) as Values
      await sendContactMessage(locale, trimmed, { signal: controller.signal })
      setValues(EMPTY)
      setTouched({})
      setServerErrors({})
      setStatus('sent')
    } catch (error) {
      if (error instanceof ApiError && error.kind === 'aborted') return
      if (error instanceof ApiError && error.kind === 'validation') {
        const fieldErrors: Partial<Record<Field, string>> = {}
        for (const field of FIELDS) {
          const message = error.fieldErrors[field]?.[0]
          if (message) fieldErrors[field] = message
        }
        setServerErrors(fieldErrors)
        const first = FIELDS.find((field) => fieldErrors[field])
        if (first) {
          setStatus('invalid')
          focusField(first)
          return
        }
      }
      setStatus(error instanceof ApiError && error.kind === 'rate_limited' ? 'rateLimited' : 'failed')
    }
  }

  const notice = {
    idle: '',
    sending: '',
    sent: t.contact.sent,
    failed: t.contact.failed,
    rateLimited: t.contact.rateLimited,
    invalid: t.contact.checkFields,
  }[status]

  const field = (name: Field, label: string, type: 'text' | 'email' | 'textarea' = 'text') => {
    const id = `${formId}-${name}`
    const error = touched[name] ? errorFor(name) : null
    const common = {
      id,
      name,
      value: values[name],
      maxLength: LIMITS[name].max,
      required: true,
      'aria-invalid': Boolean(error),
      'aria-describedby': error ? `${id}-error` : undefined,
      onChange: (event: { target: { value: string } }) => {
        setValues((v) => ({ ...v, [name]: event.target.value }))
        setServerErrors((v) => ({ ...v, [name]: undefined }))
        if (status === 'sent') setStatus('idle')
      },
      onBlur: () => setTouched((v) => ({ ...v, [name]: true })),
      className:
        'w-full rounded-xl border border-white/10 bg-white/[0.04] px-4 py-3 text-white placeholder:text-slate-500 transition focus:border-cyan-300/60 focus:bg-white/[0.07] focus:outline-none aria-[invalid=true]:border-rose-400/60',
    }
    return (
      <div className={name === 'message' ? 'sm:col-span-2' : undefined}>
        <label htmlFor={id} className="mb-2 block text-sm font-medium text-slate-200">
          {label}
        </label>
        {type === 'textarea' ? (
          <textarea {...common} rows={6} />
        ) : (
          <input {...common} type={type} autoComplete={name === 'name' ? 'name' : name === 'email' ? 'email' : 'off'} />
        )}
        {error && (
          <p id={`${id}-error`} className="mt-2 text-sm text-rose-300">
            {error}
          </p>
        )}
      </div>
    )
  }

  return (
    <section id="contact" aria-labelledby="contact-title" className="relative py-28">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <div className="grid gap-12 lg:grid-cols-[1fr_1.4fr]">
          <div>
            <SectionHeading id="contact-title" eyebrow={t.contact.eyebrow} title={t.contact.title} />
            <Reveal>
              <p className="-mt-6 max-w-md leading-relaxed text-slate-400">{t.contact.text}</p>
            </Reveal>
          </div>

          <Reveal delay={0.1}>
            <form
              noValidate
              onSubmit={onSubmit}
              aria-busy={status === 'sending'}
              className="glass grid gap-5 rounded-3xl p-6 sm:grid-cols-2 sm:p-8"
            >
              {field('name', t.contact.name)}
              {field('email', t.contact.email, 'email')}
              <div className="sm:col-span-2">{field('subject', t.contact.subject)}</div>
              {field('message', t.contact.message, 'textarea')}
              <div className="flex flex-col gap-4 sm:col-span-2">
                <button
                  type="submit"
                  disabled={status === 'sending'}
                  className="inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-violet-500 to-cyan-400 px-7 py-3.5 font-semibold text-ink-950 transition hover:brightness-110 disabled:cursor-wait disabled:opacity-70 sm:w-auto sm:self-start"
                >
                  {status === 'sending' ? t.contact.sending : t.contact.send}
                </button>
                <p role="status" className={`text-sm ${status === 'sent' ? 'text-emerald-300' : 'text-amber-200/90'}`}>
                  {notice}
                </p>
              </div>
            </form>
          </Reveal>
        </div>
      </div>
    </section>
  )
}
