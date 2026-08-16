<?php

namespace App\Http\Requests\InventoryMovement;

use App\DTOs\InventoryMovement\MovementLineDTO;
use App\DTOs\InventoryMovement\RegisterMovementsDTO;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CreateMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idProduct'                    => 'required|integer|exists:products,id',
            'idUser'                       => 'required|integer|exists:users,id',
            'referenceType'                => 'nullable|string|max:50',
            'referenceId'                  => 'nullable|integer',
            'notes'                        => 'nullable|string',
            'movements'                    => 'required|array|min:1|max:200',
            'movements.*.idProductVariant' => 'required|integer|distinct|exists:product_variants,id',
            'movements.*.movementType'     => 'required|in:entry,sale,adjustment,return,cancel',
            'movements.*.quantity'         => 'required|numeric|gt:0',
        ];
    }

    /**
     * `idUser` debe corresponder al usuario autenticado: no se acepta que un
     * admin atribuya el movimiento a otra persona.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $sessionUserId = $this->user()?->id;

            if ($sessionUserId !== null && (int) $this->input('idUser') !== (int) $sessionUserId) {
                $validator->errors()->add('idUser', 'El usuario no corresponde a la sesión activa.');
            }
        }];
    }

    public function toDTO(): RegisterMovementsDTO
    {
        $lines = array_map(
            fn (array $line) => new MovementLineDTO(
                productVariantId: (int) $line['idProductVariant'],
                movementType:     $line['movementType'],
                quantity:         (float) $line['quantity'],
            ),
            $this->input('movements'),
        );

        return new RegisterMovementsDTO(
            productId:      (int) $this->input('idProduct'),
            userId:         (int) $this->input('idUser'),
            referenceType:  $this->input('referenceType'),
            referenceId:    $this->filled('referenceId') ? (int) $this->input('referenceId') : null,
            notes:          $this->input('notes'),
            lines:          $lines,
        );
    }
}
