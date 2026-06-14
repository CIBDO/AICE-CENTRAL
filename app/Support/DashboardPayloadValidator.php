<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validation rapide des payloads push régionaux (évite mouvements.* / banques.* sur gros volumes).
 */
final class DashboardPayloadValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        $validator = Validator::make($payload, [
            'local_id' => 'required|string|max:100',
            'regional_id' => 'required|string|max:100',
            'total_ordonnance' => 'required|numeric|min:0',
            'total_recouvrements_4121' => 'required|numeric|min:0',
            'total_montant_paye' => 'required|numeric|min:0',
            'solde' => 'required|numeric',
            'tresorerie_reelle' => 'required|numeric',
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'mouvements' => 'present|array',
            'banques' => 'nullable|array',
            'recettes_clients' => 'nullable|array',
            'chunk_info' => 'nullable|array',
            'chunk_info.current' => 'nullable|integer|min:1',
            'chunk_info.total' => 'nullable|integer|min:1',
            'chunk_info.chunk_size' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $errors = [];

        foreach ($payload['mouvements'] as $index => $mouvement) {
            $this->validateMouvement($mouvement, (int) $index, $errors);
        }

        foreach ($payload['banques'] ?? [] as $index => $banque) {
            $this->validateBanque($banque, (int) $index, $errors);
        }

        foreach ($payload['recettes_clients'] ?? [] as $index => $recette) {
            $this->validateRecetteClient($recette, (int) $index, $errors);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payload;
    }

    /** @param  array<string, mixed>  $errors */
    private function validateMouvement(mixed $mouvement, int $index, array &$errors): void
    {
        if (!is_array($mouvement)) {
            $errors["mouvements.{$index}"] = ['Chaque mouvement doit être un objet.'];

            return;
        }

        $prefix = "mouvements.{$index}";

        if (empty($mouvement['regional_id']) || !is_string($mouvement['regional_id'])) {
            $errors["{$prefix}.regional_id"] = ['Le regional_id est requis.'];
        } elseif (strlen($mouvement['regional_id']) > 100) {
            $errors["{$prefix}.regional_id"] = ['Le regional_id ne doit pas dépasser 100 caractères.'];
        }

        if (!isset($mouvement['libelle']) || $mouvement['libelle'] === '') {
            $errors["{$prefix}.libelle"] = ['Le libellé est requis.'];
        }

        if (!isset($mouvement['montant']) || !is_numeric($mouvement['montant'])) {
            $errors["{$prefix}.montant"] = ['Le montant doit être un nombre.'];
        }

        $type = $mouvement['type'] ?? null;
        if (!in_array($type, ['recette', 'depense'], true)) {
            $errors["{$prefix}.type"] = ['Le type doit être recette ou depense.'];
        }

        if (isset($mouvement['type_mandat']) && $mouvement['type_mandat'] !== null
            && !in_array((string) $mouvement['type_mandat'], ['0', '1', '2'], true)) {
            $errors["{$prefix}.type_mandat"] = ['Le type_mandat doit être 0, 1 ou 2.'];
        }
    }

    /** @param  array<string, mixed>  $errors */
    private function validateBanque(mixed $banque, int $index, array &$errors): void
    {
        if (!is_array($banque)) {
            $errors["banques.{$index}"] = ['Chaque banque doit être un objet.'];

            return;
        }

        $prefix = "banques.{$index}";

        if (empty($banque['numero_compte'])) {
            $errors["{$prefix}.numero_compte"] = ['Le numéro de compte est requis.'];
        }

        if (empty($banque['libelle'])) {
            $errors["{$prefix}.libelle"] = ['Le libellé est requis.'];
        }
    }

    /** @param  array<string, mixed>  $errors */
    private function validateRecetteClient(mixed $recette, int $index, array &$errors): void
    {
        if (!is_array($recette)) {
            $errors["recettes_clients.{$index}"] = ['Chaque recette client doit être un objet.'];

            return;
        }

        $prefix = "recettes_clients.{$index}";

        if (empty($recette['client_no'])) {
            $errors["{$prefix}.client_no"] = ['Le client_no est requis.'];
        }

        if (empty($recette['client_name'])) {
            $errors["{$prefix}.client_name"] = ['Le client_name est requis.'];
        }
    }
}
