<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'dossier_id',
        'title',
        'type',
        'status',
        'last_message_at',
        'is_important',
        'created_by',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'is_important' => 'boolean',
    ];

    // ✅ CORRECTION : Ne pas inclure unread_count dans appends par défaut
    // protected $appends = ['unread_count', 'last_message_preview'];

    // Relations
    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function report()
    {
        return $this->belongsTo(Report::class, 'reference', 'reference');
    }

    public function participants()
    {
        return $this->hasMany(ChatParticipant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_participants');
    }

    // Attributs calculés
    public function getUnreadCountAttribute()
    {
        // ✅ CORRECTION : Vérifier si l'utilisateur est authentifié
        if (!Auth::check()) return 0;

        return $this->messages()
            ->where('sender_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->count();
    }

    public function getLastMessagePreviewAttribute()
    {
        $lastMessage = $this->messages()->latest('created_at')->first();

        if (!$lastMessage) return 'Aucun message';

        if ($lastMessage->type === 'file') {
            return '📎 Fichier joint';
        }

        if ($lastMessage->type === 'image') {
            return '🖼️ Image jointe';
        }

        return $lastMessage->content;
    }

    // Scopes
    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->whereHas('participants', function($participantQuery) use ($userId) {
                $participantQuery->where('user_id', $userId);
            })
                ->orWhere('type', 'support');
        });
    }

    public function scopeWithUnread($query, $userId)
    {
        return $query->whereHas('messages', function ($q) use ($userId) {
            $q->where('sender_id', '!=', $userId)
                ->whereNull('read_at');
        });
    }

    public function scopeWithDossier($query)
    {
        return $query->with(['dossier', 'messages.sender', 'participants.user']);
    }

    public function scopeSupport($query)
    {
        return $query->where('type', 'support')
            ->where('status', 'active');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Méthodes utilitaires
    public function markAsReadForUser($userId)
    {
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function toggleImportant()
    {
        $this->is_important = !$this->is_important;
        $this->save();

        return $this->is_important;
    }

    public function addParticipant($userId, $role = 'member')
    {
        return ChatParticipant::firstOrCreate([
            'chat_id' => $this->id,
            'user_id' => $userId,
        ], [
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    public function hasParticipant($userId)
    {
        return $this->participants()
            ->where('user_id', $userId)
            ->exists();
    }

    public function getMessagesCountAttribute()
    {
        return $this->messages()->count();
    }

    public function getLastMessageAttribute()
    {
        return $this->messages()->latest('created_at')->first();
    }

    public function isSupportChat()
    {
        return $this->type === 'support';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function close($userId = null)
    {
        $this->status = 'closed';
        $this->closed_by = $userId ?? Auth::id();
        $this->closed_at = now();
        $this->save();
    }

    public function reopen()
    {
        $this->status = 'active';
        $this->closed_by = null;
        $this->closed_at = null;
        $this->save();
    }

    public function getSharedFiles()
    {
        return $this->messages()
            ->whereIn('type', ['file', 'image'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($message) {
                return [
                    'id' => $message->id,
                    'name' => $message->file_name,
                    'type' => $message->type,
                    'size' => $message->file_size,
                    'path' => $message->file_path,
                    'url' => $message->file_path ? \Storage::url($message->file_path) : null,
                    'uploaded_by' => $message->sender ? $message->sender->full_name : $message->sender_name,
                    'uploaded_at' => $message->created_at,
                ];
            });
    }

    public function getUnreadCountForUser($userId)
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function hasUnreadMessages($userId)
    {
        return $this->getUnreadCountForUser($userId) > 0;
    }
}
