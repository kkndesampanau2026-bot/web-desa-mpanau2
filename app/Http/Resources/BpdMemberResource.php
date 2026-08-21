<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Anggota BPD untuk konsumsi publik — PRD 6.2. */
class BpdMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'jabatan' => $this->jabatan,
            'foto' => $this->foto ? asset('storage/'.$this->foto) : null,
            'dapil' => $this->dapil,
            'periode_mulai' => $this->periode_mulai?->toDateString(),
            'periode_selesai' => $this->periode_selesai?->toDateString(),
        ];
    }
}
