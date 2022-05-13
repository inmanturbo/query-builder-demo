<?php

namespace Rappasoft\LaravelLivewireTables\Traits;

use Rappasoft\LaravelLivewireTables\Traits\Configuration\ColumnSelectConfiguration;
use Rappasoft\LaravelLivewireTables\Traits\Helpers\ColumnSelectHelpers;

trait WithColumnSelect
{
    use ColumnSelectConfiguration,
        ColumnSelectHelpers;

    public array $selectedColumns = [];
    protected bool $columnSelectStatus = true;
    protected bool $rememberColumnSelectionStatus = true;

    public function setupColumnSelect(): void
    {
        $columns = collect($this->getColumns())
            ->filter(function ($column) {
                return $column->isVisible() && $column->isSelectable() && $column->isSelected();
            })
            ->map(fn ($column) => $column->getField())
            ->values()
            ->toArray();

        // Set to either the default set or what is stored in the session
        $this->selectedColumns = count($this->userSelectedColumns) > 0 ? $this->userSelectedColumns : $columns;
    }

    public function updatedSelectedColumns(): void
    {
        $this->userSelectedColumns = $this->selectedColumns;
    }
}
