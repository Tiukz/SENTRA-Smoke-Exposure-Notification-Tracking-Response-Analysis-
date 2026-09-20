Redesign and polish the existing SENTRA dashboard frontend only.
The backend is FROZEN and MUST NOT be modified.
The goal is to transform the current technical dashboard into a clear, user-centered decision-support interface that can be understood by ordinary users without understanding hotspot algorithms, cross-track distance, projection formulas, or backend terminology.
ABSOLUTE BACKEND FREEZE
You may modify only frontend presentation files, primarily:
- resources/views/dashboard.blade.php
- frontend CSS inside the Blade view or existing frontend stylesheet
- frontend JavaScript used only for presentation/interactions
You MUST NOT modify:
- app/Services/*
- app/Contracts/*
- app/Http/Controllers/*
- app/Http/Requests/*
- app/Models/*
- migrations
- providers
- routes
- config
- .env
- API response structures
- snapshot behavior
- routing backend
- tests related to backend formulas
Do not change, duplicate, or reimplement any backend calculation in JavaScript.
The frontend must consume existing backend values exactly as provided.
Do not add new endpoints.
Do not rename backend fields.
PRODUCT GOAL
SENTRA should answer these questions in this exact mental order:
1. Apa kondisi sekarang?
2. Apakah ada area/fasilitas yang perlu diperhatikan?
3. Seberapa mendesak situasinya?
4. Siapa yang harus diprioritaskan?
5. Apa yang sebaiknya dilakukan?
6. Mengapa SENTRA memberikan hasil tersebut?
7. Jika harus berpindah, adakah rute dengan paparan proyeksi lebih rendah?
Technical information must be secondary.
1. TOP AREA STATUS
The first thing users see below the header must be one clear status card.
Use existing backend:
- summary.status
- high_risk_count
- critical_urgency_count
- nearest_eta_minutes
- top_priority_facility
Translate statuses into user-facing Indonesian:
SAFE
→ Kondisi Terkendali

WATCH
→ Perlu Dipantau

ALERT
→ Perlu Kesiapsiagaan

CRITICAL
→ Tindakan Segera Diperlukan
Do NOT display SAFE, WATCH, ALERT, or CRITICAL as the primary text.
Example:
KONDISI TERKENDALI

Tidak ada fasilitas prioritas tinggi
pada horizon proyeksi saat ini.

15 hotspot terpantau
0 fasilitas risiko tinggi
0 membutuhkan tindakan segera
Example dangerous state:
TINDAKAN SEGERA DIPERLUKAN

Potensi paparan terdekat diperkirakan
dalam 38 menit.

Prioritas utama:
Puskesmas Bukit Hindu

Tindak sebelum 14.35 WITA
Keep it concise.
2. MAP IS THE MAIN VISUAL
Keep the existing Leaflet map as the primary visual element.
The map should be large and immediately visible.
Preserve:
- hotspot markers
- smoke projection corridor
- facilities
- route lines
- current Leaflet behavior
Do not change geographic calculations.
Improve visual hierarchy only.
Provide a compact map legend:
● Hotspot
▰ Proyeksi asap
● Fasilitas
━ Rute paparan lebih rendah
Avoid technical GIS terminology.
3. SIMPLE CURRENT CONDITION BAR
Above or directly beside the map, show a compact condition row.
Example:
15 Hotspot
Angin Barat 248°
1 km/jam
Data diperbarui 18 menit lalu
Use existing freshness metadata.
Prefer relative human-readable wording:
- 12 menit lalu
- 2 jam lalu
Instead of exposing raw timestamps everywhere.
Exact timestamps can be available in details.
4. DATA MODE CONTROL
Keep:
- Snapshot Riil
- Data Aktual
- Simulasi
But make it understandable.
Add short supporting text:
Snapshot Riil
Data nyata yang telah disimpan

Data Aktual
Mengambil data terbaru dari sumber eksternal

Simulasi
Menguji skenario perubahan kondisi
Do not expose provider implementation details here.
NASA FIRMS / Open-Meteo / OSM attribution may remain in a small Sumber Data section.
5. FACILITY PRIORITY SECTION
Replace dense technical facility lists with:
Prioritas Respons
Show only the Top 3 facilities initially.
Each card should answer:
#1 Puskesmas Bukit Hindu

Risiko: Tinggi
Urgensi: Kritis

Potensi paparan:
±38 menit

TINDAK SEBELUM
14.35 WITA

[Lihat tindakan]
[Mengapa?]
Other facilities should be behind:
Lihat semua fasilitas
Do not force users to interpret ranking algorithms.
6. RISK AND URGENCY LANGUAGE
Risk and urgency represent different concepts.
Make this understandable without a long technical explanation.
Use:
Risiko
Seberapa dekat fasilitas dengan jalur proyeksi asap.

Urgensi
Seberapa cepat potensi paparan dapat mencapai fasilitas.
Keep this explanation in an info tooltip, small helper, or collapsible section.
Do NOT combine risk and urgency into a fake single score.
7. ADAPTIVE RESPONSE PLAN
Rename presentation to:
Rekomendasi Tindakan
Do NOT call this AI.
Present the most important actions first.
Example:
Rekomendasi Tindakan

Puskesmas Bukit Hindu

1. Tutup akses udara luar yang tidak diperlukan.
2. Persiapkan area dalam ruangan.
3. Pantau perubahan proyeksi.

Tindakan disesuaikan dengan kondisi fasilitas
dan waktu potensi paparan.
Avoid displaying internal planner terminology.
8. WHY THIS RESULT
Use existing why_this_result.
Add a button:
Mengapa status ini muncul?
Opening it should show a short user-friendly explanation.
Example:
Mengapa?

Fasilitas berada dekat dengan jalur
proyeksi asap dan berada di depan arah
pergerakan angin.

Potensi paparan diperkirakan sekitar
38 menit dari sekarang.
Technical values such as cross-track distance may be placed under:
Lihat detail teknis
9. TECHNICAL DETAILS MUST BE COLLAPSIBLE
Move technical data away from primary UI:
- latitude / longitude
- cross-track distance
- along-track distance
- raw bearing
- projection formulas
- provider codes
- fingerprints
- scenario version
- raw JSON-like metadata
Put these inside:
Detail Teknis
collapsed by default.
Ordinary users should never need to open it.
10. SMOKE PROJECTION WORDING
Do not present the corridor as guaranteed smoke coverage.
Preferred wording:
Koridor Proyeksi Asap
Supporting text:
Area perkiraan pergerakan asap berdasarkan kondisi angin saat ini.
For projection endpoint popup use:
Batas Proyeksi

Horizon: +2 jam
Jarak proyeksi: ±2 km
Arah: 248°

Berdasarkan kondisi angin saat ini.
Add subtle disclaimer:
Bukan batas ilmiah pasti sebaran asap.
11. WHAT-IF SIMULATOR
Rename section:
Coba Skenario
Supporting text:
Lihat bagaimana perubahan angin dapat memengaruhi area yang berpotensi terdampak.
Inputs remain exactly connected to existing backend behavior.
Keep:
- direction
- speed
- horizon
But use human labels:
Arah angin
Kecepatan angin
Waktu proyeksi
Buttons:
Terapkan Skenario
Kembali ke Kondisi Saat Ini
Do not add any calculation in JavaScript.
12. EXPOSURE-AWARE ROUTE UI
Integrate the existing route endpoint without changing it.
User-facing name:
Rute Paparan Lebih Rendah
Supporting text:
Bandingkan rute berdasarkan seberapa banyak lintasan yang melewati koridor proyeksi asap.
Workflow:
Pilih titik awal
↓
Pilih tujuan
↓
Cari rute
Allow map click interaction using the existing route frontend integration if available.
Show a compact comparison:
RUTE TERCEPAT

18 menit · 12,4 km
Melewati koridor: 42%
Paparan proyeksi: Tinggi


RUTE PAPARAN LEBIH RENDAH

23 menit · 14,1 km
Melewati koridor: 8%
Paparan proyeksi: Rendah

+5 menit perjalanan
↓34 poin persentase overlap

DIREKOMENDASIKAN
Prefer wording:
Melewati koridor
rather than implying actual inhaled exposure.
Include disclaimer:
Perbandingan berdasarkan proyeksi geometris, bukan pengukuran dosis paparan.
Do not implement GPS tracking or navigation.
13. SOURCE / TRUST SECTION
Add a small unobtrusive section near the bottom:
Sumber Data
Display existing provenance:
Hotspot
NASA FIRMS

Cuaca
Open-Meteo

Fasilitas
OpenStreetMap
Also show mode and last update.
Do not overwhelm the top dashboard with provider names.
14. METHODOLOGY & DISCLAIMER
Keep methodology available, but secondary.
Use collapsible:
Tentang Perhitungan SENTRA
Explain simply:
- SENTRA projects potential movement based on current wind.
- It estimates which facilities lie near the projected path.
- It estimates time until the projected path reaches their position.
- It supports preparedness decisions.
Clearly retain:
SENTRA adalah prototipe sistem pendukung keputusan dan bukan pengganti informasi resmi dari instansi berwenang.
15. VISUAL DESIGN
Preserve SENTRA's existing identity.
Target visual direction:
- modern
- clean
- environmental
- calm
- credible
- emergency information without looking alarmist
Maintain forest green as the primary identity.
Use amber/red only for actual warning states.
Avoid excessive gradients, glassmorphism, neon, or decorative effects.
Use:
- generous whitespace
- clear typography
- rounded but professional cards
- subtle borders
- minimal shadows
- consistent iconography
Information hierarchy is more important than decoration.
16. DESKTOP LAYOUT
Recommended hierarchy:
HEADER
SENTRA + active mode

┌──────────────────────────────────────────┐
│ AREA STATUS                              │
│ Kondisi Terkendali / Perlu Kesiapsiagaan│
│ concise explanation                     │
└──────────────────────────────────────────┘

Current Conditions

┌───────────────────────┬──────────────────┐
│                       │ Prioritas        │
│        MAP            │ Respons          │
│                       │ Top 3            │
│                       │ facilities       │
└───────────────────────┴──────────────────┘

Rekomendasi Tindakan

Coba Skenario

Rute Paparan Lebih Rendah

Sumber Data

Tentang Perhitungan SENTRA
Map should receive more width than side panel.
17. MOBILE
Dashboard must remain understandable on mobile.
Order:
Status
↓
Current condition
↓
Map
↓
Priority
↓
Recommendations
↓
Scenario
↓
Route
Avoid horizontal page scrolling.
Large tables should not be required.
Prefer cards.
18. EMPTY / SAFE STATES
Safe states must not look broken.
If there are no high-risk facilities:
Belum ada fasilitas dengan risiko tinggi
pada horizon proyeksi saat ini.

Tetap pantau perubahan kondisi angin
dan pembaruan hotspot.
If route alternatives are unavailable:
Alternatif rute belum tersedia.
If external routing is unavailable:
use the existing backend error and show:
Layanan rute sementara tidak tersedia. Informasi SENTRA lainnya tetap dapat digunakan.
19. LOADING STATES
Provide simple loading feedback for existing async actions:
- switching Live Data
- scenario calculation
- route calculation
Example:
Memperbarui kondisi…
Menghitung skenario…
Membandingkan rute…
Prevent accidental duplicate submissions while loading.
This is frontend-only behavior.
20. DO NOT ADD
Do not add:
- login
- user profiles
- charts just for decoration
- new backend features
- new API calls
- GPS tracking
- notifications
- WebSockets
- AI chatbot
- new databases
- new dependencies unless absolutely required
Work with what SENTRA already has.
21. FINAL VALIDATION
Before finishing:
1. Confirm no backend PHP/service/controller/provider/config/route file was modified.
2. Confirm no calculation was duplicated in JavaScript.
3. Confirm all existing backend endpoints still work unchanged.
4. Confirm Snapshot Riil, Data Aktual, and Simulasi still work.
5. Confirm What-if Simulator still works.
6. Confirm Exposure-Aware Route Planner still works.
7. Check browser console for JavaScript errors.
8. Verify desktop and mobile layout.
9. Run existing test suite without changing backend tests.
Final report must include:
- frontend files modified
- major UI changes
- responsive behavior
- loading/error states
- existing backend tests result
- explicit confirmation:
“No SENTRA backend files or backend calculations were modified.”