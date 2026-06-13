<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateRegionTokenCommand extends Command
{
    protected $signature = 'aice:region-token
                            {code : Code région (ex. SAN, RGF)}
                            {--regenerate : Remplacer le token existant}
                            {--show : Afficher le token (copier dans AICE-API/.env → CENTRAL_API_TOKEN)}';

    protected $description = 'Génère ou affiche le token Push d\'une région (source de vérité : hub central)';

    public function handle(): int
    {
        $code = strtoupper((string) $this->argument('code'));

        $region = Region::query()->where('code', $code)->first();

        if (! $region) {
            $this->error("Région « {$code} » introuvable dans la table regions.");

            return self::FAILURE;
        }

        if (empty($region->getRawOriginal('token')) || $this->option('regenerate')) {
            $token = Str::random(64);
            $region->forceFill(['token' => $token])->save();
            $this->info($this->option('regenerate') ? 'Token régénéré.' : 'Token créé.');
        } else {
            $token = $region->getRawOriginal('token');
            $this->comment('Token existant conservé (utilisez --regenerate pour en créer un nouveau).');
        }

        if ($this->option('show') || $this->option('regenerate') || empty($region->getRawOriginal('token'))) {
            $this->newLine();
            $this->line('Région : '.$region->nom.' ('.$region->code.')');
            $this->line('Token Push (hub central) :');
            $this->line($token);
            $this->newLine();
            $this->comment('Copiez dans AICE-API/.env :');
            $this->line('CENTRAL_API_TOKEN='.$token);
        } else {
            $this->info('Token configuré (masqué). Relancez avec --show pour l\'afficher.');
        }

        return self::SUCCESS;
    }
}
