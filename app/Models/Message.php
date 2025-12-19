<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'sender_id',
        'sender_name',
        'sender_email',
        'content',
        'type',
        'reference',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'is_public',
        'status',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_public' => 'boolean',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $appends = ['time_formatted', 'is_me', 'avatar_url'];

    // =========================================
    // ✅ HOOK POUR CONVERTIR LES DONNÉES AVANT INSERTION
    // =========================================

    /**
     * Override de la méthode fill pour convertir les clés
     */
    public function fill(array $attributes)
    {
        // Convertir filepath -> file_path
        if (isset($attributes['filepath'])) {
            $attributes['file_path'] = $attributes['filepath'];
            unset($attributes['filepath']);
        }

        // Convertir filename -> file_name
        if (isset($attributes['filename'])) {
            $attributes['file_name'] = $attributes['filename'];
            unset($attributes['filename']);
        }

        // Convertir filesize -> file_size
        if (isset($attributes['filesize'])) {
            $attributes['file_size'] = $attributes['filesize'];
            unset($attributes['filesize']);
        }

        // Convertir filetype -> file_type
        if (isset($attributes['filetype'])) {
            $attributes['file_type'] = $attributes['filetype'];
            unset($attributes['filetype']);
        }

        // Convertir sendername -> sender_name
        if (isset($attributes['sendername'])) {
            $attributes['sender_name'] = $attributes['sendername'];
            unset($attributes['sendername']);
        }

        // Convertir senderemail -> sender_email
        if (isset($attributes['senderemail'])) {
            $attributes['sender_email'] = $attributes['senderemail'];
            unset($attributes['senderemail']);
        }

        return parent::fill($attributes);
    }

    // =========================================
    // ✅ ACCESSEURS POUR LECTURE (sans underscore)
    // =========================================

    public function getFilepathAttribute()
    {
        return $this->attributes['file_path'] ?? null;
    }

    public function getFilenameAttribute()
    {
        return $this->attributes['file_name'] ?? null;
    }

    public function getFilesizeAttribute()
    {
        return $this->attributes['file_size'] ?? null;
    }

    public function getFiletypeAttribute()
    {
        return $this->attributes['file_type'] ?? null;
    }

    public function getSendernameAttribute()
    {
        return $this->attributes['sender_name'] ?? null;
    }

    public function getSenderemailAttribute()
    {
        return $this->attributes['sender_email'] ?? null;
    }

    // =========================================
    // RELATIONS
    // =========================================

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // =========================================
    // ATTRIBUTS CALCULÉS
    // =========================================

    public function getTimeFormattedAttribute()
    {
        return $this->created_at->format('H:i');
    }

    public function getIsMeAttribute()
    {
        return $this->sender_id === auth()->id();
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->sender) {
            return $this->sender->avatar_url;
        }

        if (empty($this->sender_name) || $this->sender_name === 'Anonyme') {
            return 'https://ui-avatars.com/api/?name=Anonyme&background=94a3b8&color=fff';
        }

        if ($this->sender_name) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($this->sender_name) . '&background=3b82f6&color=fff';
        }

        return 'https://ui-avatars.com/api/?name=Support+FOSIKA&background=4c7026&color=fff';
    }

    // =========================================
    // MÉTHODES UTILITAIRES
    // =========================================

    public function markAsRead()
    {
        if (!$this->read_at) {
            $this->update([
                'read_at' => now(),
                'status' => 'read',
                'delivered_at' => $this->delivered_at ?? now(),
            ]);

            $this->chat->update(['last_message_at' => now()]);
        }
    }

    public function markAsDelivered()
    {
        if (!$this->delivered_at) {
            $this->update([
                'delivered_at' => now(),
                'status' => 'delivered',
            ]);
        }
    }

    public function isFile()
    {
        return in_array($this->type, ['file', 'image', 'video']);
    }

    public function getFileInfo()
    {
        if (!$this->isFile()) return null;

        return [
            'name' => $this->file_name,
            'type' => $this->file_type,
            'size' => $this->formatFileSize($this->file_size),
            'path' => $this->file_path,
            'url' => url('api/files/public/' . basename($this->file_path)),
            'preview' => $this->type === 'image' ? url('api/files/public/' . basename($this->file_path)) : null,
            'icon' => $this->getFileIcon(),
        ];
    }

    private function getFileIcon()
    {
        return match($this->type) {
            'image' => '🖼️',
            'video' => '🎥',
            'file' => '📎',
            default => '📄',
        };
    }

    private function formatFileSize($bytes)
    {
        if ($bytes == 0) return "0 Bytes";

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public function isFromVisitor()
    {
        return $this->sender_id === null;
    }

    public function isFromSupport()
    {
        return $this->sender_id !== null;
    }

    public function getSenderDisplayName()
    {
        if ($this->sender) {
            return $this->sender->full_name;
        }

        if (empty($this->sender_name) || $this->sender_name === 'Anonyme') {
            return 'Visiteur anonyme';
        }

        return $this->sender_name ?? 'Visiteur';
    }
}
