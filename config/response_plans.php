<?php

return [
    'facilities' => [
        'school' => [
            'CRITICAL' => [
                'Hentikan seluruh kegiatan luar ruang segera.',
                'Pindahkan siswa dan staf ke ruang dalam yang paling terlindungi.',
                'Beri tahu staf dan wali murid tentang kesiapan respons paparan asap.',
                'Siapkan langkah perlindungan kualitas udara dalam ruangan.',
            ],
            'HIGH' => [
                'Batasi kegiatan luar ruang dan siapkan pemindahan kegiatan ke dalam ruangan.',
                'Beri tahu staf mengenai potensi paparan dan waktu respons yang tersedia.',
                'Periksa kesiapan ruang dalam dan prosedur komunikasi kepada wali murid.',
            ],
            'MODERATE' => [
                'Tinjau jadwal kegiatan luar ruang selama horizon proyeksi.',
                'Siapkan ruang dalam jika tingkat urgensi meningkat.',
                'Pastikan staf mengetahui jalur komunikasi respons asap.',
            ],
            'LOW' => [
                'Pantau pembaruan proyeksi sebelum kegiatan luar ruang dimulai.',
                'Pastikan prosedur paparan asap dapat diaktifkan bila diperlukan.',
                'Jaga informasi kontak staf dan wali murid tetap siap digunakan.',
            ],
        ],
        'hospital' => [
            'CRITICAL' => [
                'Prioritaskan perlindungan pasien dengan gangguan pernapasan dan kelompok rentan.',
                'Periksa kesiapan ventilasi, filtrasi udara, dan area dalam yang terlindungi.',
                'Batasi perpindahan luar ruang yang tidak diperlukan.',
                'Beri tahu staf operasional terkait agar prosedur paparan asap segera dijalankan.',
            ],
            'HIGH' => [
                'Siagakan perlindungan bagi pasien rentan terhadap paparan asap.',
                'Tinjau kesiapan ventilasi dan filtrasi udara fasilitas.',
                'Batasi kegiatan operasional luar ruang yang tidak diperlukan.',
            ],
            'MODERATE' => [
                'Pantau kondisi pasien rentan dan kesiapan area dalam.',
                'Periksa jadwal pemeliharaan ventilasi serta filtrasi udara.',
                'Informasikan potensi paparan kepada penanggung jawab operasional.',
            ],
            'LOW' => [
                'Pantau proyeksi dan perubahan kondisi di sekitar fasilitas.',
                'Pastikan daftar pasien rentan dan prosedur perlindungan tersedia.',
                'Pertahankan kesiapan komunikasi internal tanpa mengganggu layanan rutin.',
            ],
        ],
        'residential' => [
            'CRITICAL' => [
                'Imbau warga untuk segera mengurangi aktivitas luar ruang.',
                'Prioritaskan anak-anak, lansia, dan warga dengan kondisi rentan.',
                'Siapkan penutupan bukaan yang tidak diperlukan jika asap mencapai area.',
                'Sebarkan pemberitahuan kesiapsiagaan melalui saluran komunikasi lingkungan.',
            ],
            'HIGH' => [
                'Imbau warga membatasi aktivitas luar ruang selama periode proyeksi.',
                'Siapkan informasi khusus bagi anak-anak, lansia, dan kelompok rentan.',
                'Aktifkan saluran komunikasi lingkungan untuk pembaruan berikutnya.',
            ],
            'MODERATE' => [
                'Pantau arah perkembangan asap dan informasi proyeksi terbaru.',
                'Ingatkan warga rentan agar menyiapkan kegiatan di dalam ruangan.',
                'Pastikan saluran pemberitahuan lingkungan siap digunakan.',
            ],
            'LOW' => [
                'Lanjutkan pemantauan proyeksi tanpa menaikkan status kewaspadaan.',
                'Bagikan informasi dasar mengenai pengurangan paparan asap.',
                'Siapkan pembaruan warga jika risiko atau urgensi berubah.',
            ],
        ],
        'default' => [
            'CRITICAL' => [
                'Aktifkan prosedur perlindungan paparan asap yang berlaku di fasilitas.',
                'Pindahkan kegiatan yang tidak perlu ke dalam ruangan.',
                'Beri tahu penanggung jawab fasilitas dan kelompok rentan.',
            ],
            'HIGH' => [
                'Siapkan prosedur perlindungan paparan asap di fasilitas.',
                'Batasi kegiatan luar ruang yang tidak diperlukan.',
                'Beri tahu penanggung jawab fasilitas mengenai waktu respons.',
            ],
            'MODERATE' => [
                'Tinjau kesiapan prosedur paparan asap di fasilitas.',
                'Pantau pembaruan proyeksi dan kondisi kelompok rentan.',
                'Siapkan saluran komunikasi internal bila urgensi meningkat.',
            ],
            'LOW' => [
                'Pantau pembaruan proyeksi secara berkala.',
                'Pastikan prosedur dasar paparan asap tersedia.',
                'Pertahankan komunikasi rutin kepada penanggung jawab fasilitas.',
            ],
        ],
    ],

    'outside_projection' => [
        'Pantau pembaruan proyeksi tanpa mengaktifkan tindakan darurat.',
        'Pertahankan kesiapan prosedur dasar paparan asap.',
        'Tinjau kembali rekomendasi jika fasilitas masuk ke horizon proyeksi.',
    ],
];
