<?php

namespace App\Observers;

use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Model;

final class AuditObserver
{
    public function __construct(private AuditTrail $audit) {}

    public function created(Model $model): void
    {
        $this->audit->modelChange($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->audit->modelChange($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->audit->modelChange($model, 'deleted');
    }
}
