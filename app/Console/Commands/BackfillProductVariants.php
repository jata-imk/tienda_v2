<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\VariantBackfillService;
use Illuminate\Console\Command;

class BackfillProductVariants extends Command
{
    protected $signature = 'inventory:backfill-variants
        {--dry-run : Diagnostica sin escribir}
        {--user-id= : Usuario responsable de los movimientos}';

    protected $description = 'Completa variantes de talla y regulariza variantes en cero sin movimientos';

    public function handle(VariantBackfillService $backfill): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user-id');
        if (! $dryRun && (! ctype_digit((string) $userId) || ! ($user = User::find((int) $userId)))) {
            $this->error('Para aplicar indica --user-id con un usuario existente.');
            return self::INVALID;
        }

        $summary = $backfill->diagnose();
        $this->table(['Diagnóstico', 'Cantidad'], [
            ['Combinaciones faltantes', $summary['missing']],
            ['Productos con combinaciones faltantes', $summary['products']],
            ['Variantes en cero sin movimientos', $summary['zeroWithoutMovements']],
            ['Variantes con grupo incompatible', $summary['mismatches']],
            ['Productos con grupo y sin colores', count($summary['noColors'])],
        ]);
        if ($summary['noColors'] !== []) {
            $this->warn('Productos sin colores (IDs): '.implode(', ', $summary['noColors']));
        }
        if ($summary['mismatchIds'] !== []) {
            $this->warn('Variantes con grupos incompatibles (primeros 100 IDs): '.implode(', ', $summary['mismatchIds']));
        }
        if ($dryRun) {
            $this->info('Simulación: no se escribió nada.');
            return self::SUCCESS;
        }

        $result = $backfill->apply($user);
        $this->info("Variantes creadas: {$result['created']}; variantes existentes regularizadas: {$result['regularized']}.");
        return self::SUCCESS;
    }
}
