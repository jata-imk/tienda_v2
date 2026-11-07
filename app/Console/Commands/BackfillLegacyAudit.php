<?php

namespace App\Console\Commands;

use App\Services\LegacyAuditBackfill;
use Illuminate\Console\Command;

class BackfillLegacyAudit extends Command
{
    protected $signature = 'audit:backfill-legacy
        {--dry-run : Solo cuenta lo que se importaría, sin escribir}
        {--only=* : Fuentes a importar: kardex, orders, sessions (por defecto todas)}';

    protected $description = 'Importa a la bitácora el Kardex, los estados de pedidos y los inicios de sesión previos a la auditoría';

    public function handle(LegacyAuditBackfill $backfill): int
    {
        $sources = $this->option('only') ?: LegacyAuditBackfill::SOURCES;
        $invalid = array_diff($sources, LegacyAuditBackfill::SOURCES);
        if ($invalid !== []) {
            $this->error('Fuente no válida: '.implode(', ', $invalid).'. Usa: '.implode(', ', LegacyAuditBackfill::SOURCES).'.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $summary = $backfill->run(array_values($sources), $dryRun);

        $labels = [
            'inventoryEvents' => 'Eventos de movimientos de inventario',
            'receptionEvents' => 'Eventos de recepción de pedidos',
            'orderEvents' => 'Eventos de estado de pedidos',
            'loginEvents' => 'Eventos de inicio de sesión',
            'changes' => 'Cambios antes/después',
            'skippedWithoutDate' => 'Omitidos por no tener fecha',
        ];
        $this->table(['Concepto', 'Cantidad'], array_map(fn ($key) => [$labels[$key], $summary[$key]], array_keys($labels)));
        $this->info($dryRun ? 'Simulación: no se escribió nada.' : 'Importación terminada. Volver a ejecutarla no duplica registros.');

        return self::SUCCESS;
    }
}
