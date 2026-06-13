<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Region extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Connexion de base de données à utiliser pour ce modèle
     * Le modèle Region utilise toujours la base locale (connexion par défaut)
     *
     * Note: Ne pas forcer 'sqlsrv' pour permettre MySQL ou SQL Server
     * La connexion utilisée sera celle définie dans DB_CONNECTION du .env
     *
     * @var string|null
     */
    // protected $connection = null; // Utilise la connexion par défaut

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',
        'nom',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
        'db_charset',
        'source_type', // 'api' ou 'sql'
        'api_url',
        'api_key',
        'token', // Token pour authentification API Push
        'actif',
        'ordre',
        'metadata',
        'derniere_connexion',
        'derniere_erreur',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
        'db_port' => 'integer',
        'metadata' => 'array',
        'derniere_connexion' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'db_password',
        'api_key',
        'token',
    ];

    /**
     * Mutateur : Chiffre automatiquement le mot de passe avant l'enregistrement
     *
     * @param string $value
     * @return void
     */
    public function setDbPasswordAttribute($value): void
    {
        // Si la valeur est null ou vide, ne rien faire (nullable pour architecture Push)
        if (empty($value)) {
            $this->attributes['db_password'] = null;
            return;
        }

        // Si la valeur n'est pas vide et n'est pas déjà chiffrée
        // Vérifier si c'est déjà chiffré (commence par "eyJpdiI6" pour Laravel Crypt)
        if (!str_starts_with($value, 'eyJpdiI6')) {
            $this->attributes['db_password'] = Crypt::encryptString($value);
        } else {
            $this->attributes['db_password'] = $value;
        }
    }

    /**
     * Accesseur : Déchiffre le mot de passe lors de la lecture
     *
     * @param string|null $value
     * @return string|null
     */
    public function getDbPasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Si le déchiffrement échoue, logger l'erreur et retourner null
            Log::error("Erreur de déchiffrement du mot de passe pour la région {$this->code}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère le mot de passe en clair (pour usage interne uniquement)
     *
     * @return string|null
     */
    public function getDecryptedPassword(): ?string
    {
        return $this->db_password;
    }

    /**
     * Scope : Récupère uniquement les régions actives
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Récupère les régions ordonnées
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('ordre')->orderBy('nom');
    }

    /**
     * Retourne la configuration de connexion pour cette région
     *
     * @return array
     */
    public function getConnectionConfig(): array
    {
        return [
            'driver' => 'sqlsrv',
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_database,
            'username' => $this->db_username,
            'password' => $this->getDecryptedPassword(),
            'charset' => $this->db_charset ?? 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // Options de sécurité pour lecture seule (côté applicatif)
            'options' => [
                \PDO::ATTR_EMULATE_PREPARES => false, // Empêche les injections SQL
                \PDO::ATTR_STRINGIFY_FETCHES => false,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        ];
    }

    /**
     * Retourne le nom de connexion dynamique pour cette région
     * NOTE: Cette méthode est utilisée par DynamicDatabaseService
     * pour configurer les connexions dynamiques, pas par Eloquent
     *
     * @return string
     */
    public function getDynamicConnectionName(): string
    {
        return "dynamic_region_{$this->code}";
    }

    /**
     * Met à jour la date de dernière connexion
     *
     * @return void
     */
    public function updateLastConnection(): void
    {
        $this->update([
            'derniere_connexion' => now(),
            'derniere_erreur' => null,
        ]);
    }

    /**
     * Enregistre une erreur de connexion
     *
     * @param string $error
     * @return void
     */
    public function logConnectionError(string $error): void
    {
        $this->update([
            'derniere_erreur' => $error,
        ]);
    }

    /**
     * Vérifie si la région a une erreur récente
     * Ignore les erreurs SQL en mode Push (source_type = 'api')
     *
     * @return bool
     */
    public function hasRecentError(): bool
    {
        // Si pas d'erreur, retourner false
        if (empty($this->derniere_erreur)) {
            return false;
        }

        // En mode Push (API), ignorer les erreurs liées aux connexions SQL
        // Ces erreurs sont normales car il n'y a pas de connexion SQL directe en mode Push
        if ($this->source_type === 'api') {
            return $this->isSqlError($this->derniere_erreur) ? false : true;
        }

        // Pour les autres erreurs ou en mode SQL, retourner true
        return true;
    }

    /**
     * Vérifie si une erreur est une erreur SQL (à ignorer en mode Push)
     *
     * @param string $error
     * @return bool
     */
    public function isSqlError(string $error): bool
    {
        $errorLower = strtolower($error);

        // Liste des erreurs SQL à ignorer en mode Push
        $sqlErrorsToIgnore = [
            'database hosts',
            'hosts array',
            'connection',
            'sql',
            'database',
            'host',
            'could not connect',
            'connection refused',
            'unknown database',
            'access denied',
        ];

        foreach ($sqlErrorsToIgnore as $sqlError) {
            if (stripos($errorLower, $sqlError) !== false) {
                return true; // C'est une erreur SQL
            }
        }

        return false; // Ce n'est pas une erreur SQL
    }

    /**
     * Nettoie les erreurs SQL pour les régions en mode Push
     *
     * @return bool True si une erreur a été nettoyée
     */
    public function cleanSqlErrors(): bool
    {
        if ($this->source_type === 'api' && !empty($this->derniere_erreur)) {
            if ($this->isSqlError($this->derniere_erreur)) {
                $this->derniere_erreur = null;
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne un nom d'affichage complet avec le statut
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        $name = "{$this->nom} ({$this->code})";

        if (!$this->actif) {
            $name .= " [Inactif]";
        } elseif ($this->hasRecentError()) {
            $name .= " ⚠️";
        }

        return $name;
    }

    /**
     * Vérifie si la région utilise l'API
     *
     * @return bool
     */
    public function usesApi(): bool
    {
        return $this->source_type === 'api' && !empty($this->api_url);
    }

    /**
     * Vérifie si la région utilise SQL
     *
     * @return bool
     */
    public function usesSql(): bool
    {
        return $this->source_type === 'sql' || empty($this->source_type);
    }

    /**
     * Chiffre la clé API avant l'enregistrement
     *
     * @param string|null $value
     * @return void
     */
    public function setApiKeyAttribute($value): void
    {
        if (!empty($value)) {
            // Vérifier si c'est déjà chiffré
            if (!str_starts_with($value, 'eyJpdiI6')) {
                $this->attributes['api_key'] = Crypt::encryptString($value);
            } else {
                $this->attributes['api_key'] = $value;
            }
        } else {
            $this->attributes['api_key'] = null;
        }
    }

    /**
     * Déchiffre la clé API lors de la lecture
     *
     * @param string|null $value
     * @return string|null
     */
    public function getApiKeyAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            Log::error("Erreur de déchiffrement de la clé API pour la région {$this->code}: " . $e->getMessage());
            return null;
        }
    }
}

