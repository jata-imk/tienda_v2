<?php

namespace App\Services;

use App\DTOs\Config\ActualizarImpuestosConfigDTO;
use App\Models\ImpuestosConfig;
use Carbon\Carbon;

class ImpuestosConfigService
{
    public function get(): ImpuestosConfig
    {
        return ImpuestosConfig::findOrFail(1);
    }

    public function update(ActualizarImpuestosConfigDTO $dto): ImpuestosConfig
    {
        $config = ImpuestosConfig::findOrFail(1);

        $campos = array_filter([
            'iva'         => $dto->iva,
            'isr'         => $dto->isr,
            'imp_esp'     => $dto->impEsp,
            'date_update' => Carbon::now(),
        ], fn($v) => $v !== null);

        $config->update($campos);

        return $config->fresh();
    }
}
