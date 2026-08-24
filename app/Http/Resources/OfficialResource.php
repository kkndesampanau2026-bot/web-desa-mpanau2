<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Aparat desa untuk konsumsi publik — PRD 6.2. */
class OfficialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'jabatan' => $this->jabatan,
            'foto' => $this->foto ? asset('storage/'.$this->foto) : null,
            'periode_mulai' => $this->periode_mulai?->toDateString(),
            'periode_selesai' => $this->periode_selesai?->toDateString(),
            'urutan_tampil' => $this->urutan_tampil,
            // Tingkat dalam bagan struktur: 0 = puncak, membesar ke bawah.
            'tingkat' => $this->tingkat,
            // no_sk_pengangkatan sengaja TIDAK diekspos ke publik —
            // dokumen kepegawaian bukan informasi yang wajib diumumkan.
        ];
    }
}
