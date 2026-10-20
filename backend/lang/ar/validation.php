<?php

/*
 * رسائل التحقق بالعربية للوحة الإدارة (Filament). تشمل القواعد المستخدمة في المشروع فقط؛
 * أي قاعدة غير مذكورة هنا تعود إلى الرسالة الإنجليزية المرفقة مع Laravel.
 */
return [
    'array' => 'يجب أن يكون حقل :attribute قائمة.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحًا.',
    'enum' => 'القيمة المختارة في :attribute غير صالحة.',
    'exists' => 'القيمة المختارة في :attribute غير موجودة.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'القيمة المختارة في :attribute غير صالحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصرًا.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'string' => 'يجب ألا يتجاوز :attribute :max حرفًا.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصرًا على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل :attribute عن :min حرفًا.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'uploaded' => 'تعذّر رفع :attribute.',
    'url' => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة :attribute في تسريب بيانات. يرجى اختيار قيمة أخرى.',
    ],
    'attributes' => [],
];
