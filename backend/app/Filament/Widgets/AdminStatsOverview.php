<?php

namespace App\Filament\Widgets;

use App\Actions\Messages\ChangeMessageStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Technologies\TechnologyResource;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\Technology;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard figures, counted from the database on each visit (no mock data).
 */
class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $published = Project::query()->where('status', ProjectStatus::Published)->count();
        $drafts = Project::query()->where('status', ProjectStatus::Draft)->count();
        $visible = Technology::query()->where('is_visible', true)->count();
        $hidden = Technology::query()->where('is_visible', false)->count();
        $unread = ChangeMessageStatus::unreadCount();

        return [
            Stat::make(__('admin.dashboard.projects'), $published + $drafts)
                ->description(__('admin.dashboard.projects_description', ['published' => $published, 'drafts' => $drafts]))
                ->icon(Heroicon::OutlinedRectangleStack)
                ->url(ProjectResource::getUrl('index')),
            Stat::make(__('admin.dashboard.categories'), Category::query()->count())
                ->icon(Heroicon::OutlinedTag)
                ->url(CategoryResource::getUrl('index')),
            Stat::make(__('admin.dashboard.technologies'), $visible + $hidden)
                ->description(__('admin.dashboard.technologies_description', ['visible' => $visible, 'hidden' => $hidden]))
                ->icon(Heroicon::OutlinedCpuChip)
                ->url(TechnologyResource::getUrl('index')),
            Stat::make(__('admin.dashboard.messages'), $unread)
                ->description(__('admin.dashboard.messages_description', ['total' => ContactMessage::query()->count()]))
                ->icon(Heroicon::OutlinedInbox)
                ->color($unread > 0 ? 'primary' : 'gray')
                ->url(ContactMessageResource::getUrl('index')),
        ];
    }
}
