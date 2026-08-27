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
     * Bagan Pemerintah Desa dan BPD tampil sebagai gambar yang diunggah admin
     * (kolom `bagan_pemerintahan` & `bagan_bpd` pada profil), jadi halaman ini
     * tidak lagi memerlukan daftar aparat maupun anggota BPD. Keduanya tetap
     * dua bagan terpisah sesuai PRD 3.2, dan susunan aparat beserta fotonya
     * tetap disajikan halaman Pemerintah Desa di bawah.
     */
    public function index(): Response
    {
        return Inertia::render('Publik/Profil', [
            // Null bila admin belum mengisi apa pun — halaman tetap dirender
            // dengan empty-state informatif (PRD 3.2), bukan 404.
            'profil' => $this->profil->data(),
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
