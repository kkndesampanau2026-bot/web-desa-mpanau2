<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\ProfilDesa;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil Desa & Pemerintah Desa — PRD 6.1–6.2.
 */
class ProfilController extends Controller
{
    public function __construct(private readonly ProfilDesa $profil) {}

    /**
     * Sambutan, sejarah, visi-misi, data geografis, dan kedua bagan lembaga.
     *
     * Aparat desa dan BPD sengaja dikirim terpisah: PRD 3.2 menegaskan
     * keduanya lembaga berbeda yang wajib direpresentasikan masing-masing,
     * bukan digabung menjadi satu daftar pengurus.
     */
    public function index(): Response
    {
        return Inertia::render('Publik/Profil', [
            // Null bila admin belum mengisi apa pun — halaman tetap dirender
            // dengan empty-state informatif (PRD 3.2), bukan 404.
            'profil' => $this->profil->data(),
            'aparat' => $this->profil->aparat(),
            'bpd' => $this->profil->bpd(),
        ]);
    }

    /** Halaman tersendiri berisi susunan aparat desa beserta masa baktinya. */
    public function pemerintah(): Response
    {
        return Inertia::render('Publik/Pemerintah', [
            'aparat' => $this->profil->aparat(),
        ]);
    }
}
