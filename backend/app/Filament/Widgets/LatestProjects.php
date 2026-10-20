<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The five most recently updated projects (drafts included), linking to their edit page.
 */
class LatestProjects extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.latest_projects'))
            ->query(Project::query()->with('translations')->latest('updated_at')->latest('id')->limit(5))
            ->paginated(false)
            ->emptyStateHeading(__('admin.dashboard.no_projects'))
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.projects.fields.title'))
                    ->state(fn (Project $record): string => ProjectResource::title($record) ?? __('admin.projects.untitled')),
                TextColumn::make('status')
                    ->label(__('admin.projects.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => __('admin.projects.statuses.'.$state->value))
                    ->color(fn (ProjectStatus $state): string => $state === ProjectStatus::Published ? 'success' : 'warning'),
                TextColumn::make('updated_at')->label(__('admin.common.updated_at'))->since()->dateTimeTooltip(),
            ]);
    }
}
