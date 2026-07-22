<?php

namespace App\DTOs\Dashboard;

readonly class DashboardFiltersDTO
{
    public function __construct(
        public int     $limit = 5,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public float   $lowStockThreshold = 5,
    ) {}
}
