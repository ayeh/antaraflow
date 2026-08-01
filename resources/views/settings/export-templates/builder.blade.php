@extends('layouts.app')

@section('content')
<div
    x-data="templateBuilder({{ json_encode([
        'templateId'  => $template->id,
        'blocks'      => $template->blocks ?? [],
        'primaryColor'=> $template->primary_color ?? '#4c1d95',
        'fontFamily'  => $template->font_family ?? 'Arial',
        'labels'      => $template->labels ?? [],
        'pageSetup'   => $template->page_setup ?? [],
        'glossary'    => $template->glossary ?? [],
        'saveUrl'     => route('settings.export-templates.blocks.save', $template),
        'previewUrl'  => route('settings.export-templates.preview', $template),
        'logoUrl'     => route('settings.export-templates.logo', $template),
        'sampleMomId' => $sampleMomId,
        'blockTypes'  => $blockTypes,
    ]) }})"
    class="flex flex-col h-[calc(100vh-64px)]"
>
    {{-- ── Top bar ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 shrink-0">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.export-templates.index') }}"
               class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">{{ $template->name }}</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Block Builder</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{-- Language selector for preview --}}
            <select x-model="previewLanguage"
                class="text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                <option value="ms">🇲🇾 Bahasa Melayu</option>
                <option value="en">🇬🇧 English</option>
            </select>

            <button @click="openPreview()"
                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                👁 Pratonton
            </button>

            <button @click="save()"
                :disabled="saving"
                :class="saved ? 'bg-green-600 hover:bg-green-700' : 'bg-violet-600 hover:bg-violet-700'"
                class="flex items-center gap-1.5 px-4 py-1.5 text-xs font-medium rounded-lg text-white transition-colors disabled:opacity-60">
                <span x-show="saving">Menyimpan…</span>
                <span x-show="saved && !saving">✓ Disimpan</span>
                <span x-show="!saving && !saved">💾 Simpan</span>
            </button>
        </div>
    </div>

    {{-- ── Three-column body ──────────────────────────────────────────── --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- Left: Block Palette --}}
        <div class="w-52 shrink-0 border-r border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 overflow-y-auto p-3 space-y-1">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 px-1">Blok</p>
            <template x-for="bt in blockTypes" :key="bt.type">
                <button
                    @click="addBlock(bt.type)"
                    class="w-full text-left flex items-start gap-2 px-2 py-2 rounded-lg hover:bg-white dark:hover:bg-slate-800 text-sm text-gray-700 dark:text-gray-300 transition-colors group">
                    <span class="text-base leading-none mt-0.5" x-text="bt.icon"></span>
                    <div>
                        <div class="font-medium text-xs" x-text="bt.label"></div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 leading-tight" x-text="bt.description"></div>
                    </div>
                </button>
            </template>

            {{-- Page settings section --}}
            <div class="pt-4 border-t border-gray-200 dark:border-slate-700">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 px-1">Halaman</p>
                <div class="space-y-2 px-1">
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Warna Utama</label>
                        <div class="flex gap-1.5 items-center">
                            <input type="color" x-model="primaryColor"
                                class="w-7 h-7 rounded border border-gray-300 dark:border-slate-600 cursor-pointer p-0">
                            <input type="text" x-model="primaryColor"
                                class="flex-1 text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Font</label>
                        <select x-model="fontFamily"
                            class="w-full text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1">
                            <option>Arial</option>
                            <option>Calibri</option>
                            <option>Times New Roman</option>
                            <option>Georgia</option>
                            <option>Verdana</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Logo</label>
                        <label class="block cursor-pointer">
                            <input type="file" accept="image/*" @change="uploadLogo($event)" class="hidden">
                            <span class="block text-center text-xs text-violet-600 hover:text-violet-700 border border-dashed border-violet-300 rounded-lg py-2">
                                <span x-show="!logoUploading">📤 Muat naik</span>
                                <span x-show="logoUploading">Memuat naik…</span>
                            </span>
                        </label>
                        <template x-if="logoUrl">
                            <img :src="logoUrl" class="mt-1 h-8 w-auto rounded" alt="logo">
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Centre: Block list --}}
        <div class="flex-1 overflow-y-auto bg-white dark:bg-slate-950 p-4">
            <div x-show="blocks.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400 dark:text-gray-600 text-sm gap-2">
                <span class="text-4xl">🧩</span>
                <p>Tambah blok dari panel kiri untuk mula</p>
            </div>

            <div id="block-canvas" class="space-y-2">
                <template x-for="(block, index) in blocks" :key="block.id">
                    <div
                        :data-id="block.id"
                        @click="selectBlock(index)"
                        :class="selectedIndex === index
                            ? 'ring-2 ring-violet-500 bg-violet-50 dark:bg-violet-950/30'
                            : 'hover:ring-1 hover:ring-gray-300 dark:hover:ring-slate-600'"
                        class="group relative flex items-center gap-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-3 cursor-pointer transition-all"
                    >
                        {{-- Drag handle --}}
                        <span class="drag-handle cursor-grab text-gray-300 dark:text-slate-600 hover:text-gray-400 select-none" title="Seret untuk susun">⠿</span>

                        {{-- Block type icon + label --}}
                        <span class="text-lg leading-none" x-text="blockMeta(block.type)?.icon ?? '📦'"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="blockMeta(block.type)?.label ?? block.type"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 truncate" x-text="blockSummary(block)"></p>
                        </div>

                        {{-- Remove button --}}
                        <button @click.stop="removeBlock(index)"
                            class="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-600 text-sm p-1 rounded transition-opacity"
                            title="Buang">✕</button>

                        {{-- Move up/down --}}
                        <div class="opacity-0 group-hover:opacity-100 flex flex-col gap-0.5 transition-opacity">
                            <button @click.stop="moveBlock(index, -1)" :disabled="index === 0"
                                class="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-20 leading-none p-0.5">▲</button>
                            <button @click.stop="moveBlock(index, 1)" :disabled="index === blocks.length - 1"
                                class="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-20 leading-none p-0.5">▼</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Right: Settings panel --}}
        <div class="w-72 shrink-0 border-l border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-y-auto">
            <template x-if="selectedIndex === null">
                <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-600 text-sm gap-2 p-6 text-center">
                    <span class="text-3xl">👈</span>
                    <p>Pilih blok dari kanvas untuk edit tetapannya</p>
                </div>
            </template>

            <template x-if="selectedIndex !== null">
                <div class="p-4 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white"
                            x-text="blockMeta(selectedBlock()?.type)?.label ?? 'Tetapan'"></h3>
                        <button @click="selectedIndex = null" class="text-gray-400 hover:text-gray-600 text-sm">✕</button>
                    </div>

                    {{-- ── Letterhead settings ── --}}
                    <template x-if="selectedBlock()?.type === 'letterhead'">
                        <div class="space-y-3" x-data>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Penjajaran</label>
                                <select x-model="selectedBlock().settings.align"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                    <option value="left">Kiri</option>
                                    <option value="center">Tengah</option>
                                    <option value="right">Kanan</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Baris teks (satu baris per baris)</label>
                                <textarea x-model="linesText" @input="selectedBlock().settings.lines = linesText.split('\n')"
                                    x-effect="linesText = (selectedBlock().settings.lines ?? []).join('\n')"
                                    rows="3"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5 font-mono"></textarea>
                                <p class="text-xs text-gray-400 mt-1">Token: <code>@{{organization.name}}</code></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" x-model="selectedBlock().settings.show_rule" class="rounded">
                                    Tunjuk garisan bawah
                                </label>
                            </div>
                        </div>
                    </template>

                    {{-- ── Title settings ── --}}
                    <template x-if="selectedBlock()?.type === 'title'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Teks tajuk</label>
                                <textarea x-model="selectedBlock().settings.text" rows="3"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5 font-mono"></textarea>
                                <p class="text-xs text-gray-400 mt-1">Token: <code>@{{title}}</code>, <code>@{{mom_number}}</code>, <code>@{{year}}</code>, <code>@{{series.name}}</code></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Penjajaran</label>
                                <select x-model="selectedBlock().settings.align"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                    <option value="left">Kiri</option>
                                    <option value="center">Tengah</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    {{-- ── Meta settings ── --}}
                    <template x-if="selectedBlock()?.type === 'meta'">
                        <div class="space-y-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Medan lalai: Tarikh, Masa, Tempat. Tambah token tersuai di bawah.</p>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Pemisah label</label>
                                <input type="text" x-model="selectedBlock().settings.separator" maxlength="3"
                                    class="w-20 text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                            </div>
                        </div>
                    </template>

                    {{-- ── Attendees settings ── --}}
                    <template x-if="selectedBlock()?.type === 'attendees'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kumpulan kehadiran</label>
                                <div class="space-y-1">
                                    <template x-for="g in ['present','also_present','absent']" :key="g">
                                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <input type="checkbox"
                                                :checked="(selectedBlock().settings.groups ?? ['present','also_present','absent']).includes(g)"
                                                @change="toggleGroup(g)"
                                                class="rounded">
                                            <span x-text="{'present':'Hadir','also_present':'Turut Hadir','absent':'Tidak Hadir'}[g]"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- ── Agenda settings ── --}}
                    <template x-if="selectedBlock()?.type === 'agenda'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sumber tajuk agenda</label>
                                <select x-model="selectedBlock().settings.source"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                    <option value="topics">Topik mesyuarat</option>
                                    <option value="none">Tiada (kandungan AI sahaja)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Seksyen AI untuk dimasukkan</label>
                                <div class="space-y-1">
                                    <template x-for="t in ['summary','topics','decisions','risks']" :key="t">
                                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                            <input type="checkbox"
                                                :checked="(selectedBlock().settings.extraction_types ?? ['summary','topics','decisions','risks']).includes(t)"
                                                @change="toggleExtraction(t)"
                                                class="rounded">
                                            <span x-text="{'summary':'Ringkasan','topics':'Perkara Dibincangkan','decisions':'Keputusan','risks':'Risiko & Isu'}[t]"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- ── RichText settings ── --}}
                    <template x-if="selectedBlock()?.type === 'richtext'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Sumber</label>
                                <select x-model="selectedBlock().settings.source"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                    <option value="">Teks statik di bawah</option>
                                    <option value="manual_notes">Nota manual dari mesyuarat</option>
                                </select>
                            </div>
                            <div x-show="!selectedBlock().settings.source">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kandungan</label>
                                <textarea x-model="selectedBlock().settings.content" rows="5"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5 font-mono"></textarea>
                            </div>
                        </div>
                    </template>

                    {{-- ── Action Tag settings ── --}}
                    <template x-if="selectedBlock()?.type === 'action_tag'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Tag</label>
                                <input type="text" x-model="selectedBlock().settings.tag" placeholder="Makluman"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                <p class="text-xs text-gray-400 mt-1">Contoh: <em>Makluman</em>, <em>Tindakan : PJ(A)</em></p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Penjajaran</label>
                                <select x-model="selectedBlock().settings.align"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                    <option value="left">Kiri</option>
                                    <option value="right">Kanan</option>
                                    <option value="center">Tengah</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    {{-- ── Signature settings ── --}}
                    <template x-if="selectedBlock()?.type === 'signature'">
                        <div class="space-y-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Token tersedia: <code>@{{prepared_by}}</code>, <code>@{{chair.name}}</code></p>
                            <template x-for="(col, ci) in (selectedBlock().settings.columns ?? [])" :key="ci">
                                <div class="border border-gray-200 dark:border-slate-700 rounded-lg p-3 space-y-2">
                                    <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Lajur <span x-text="ci + 1"></span></p>
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Nama (token)</label>
                                        <input type="text" x-model="col.name"
                                            class="w-full text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Jawatan</label>
                                        <input type="text" x-model="col.position"
                                            class="w-full text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1">
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 mb-0.5 block">Label</label>
                                        <input type="text" x-model="col.label"
                                            class="w-full text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1">
                                    </div>
                                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" x-model="col.show_date" class="rounded"> Tunjuk baris tarikh
                                    </label>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ── Divider settings ── --}}
                    <template x-if="selectedBlock()?.type === 'divider'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Warna</label>
                                <input type="color" x-model="selectedBlock().settings.color"
                                    class="w-10 h-8 rounded border cursor-pointer p-0">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Ketebalan (px)</label>
                                <input type="number" x-model.number="selectedBlock().settings.thickness" min="1" max="8"
                                    class="w-20 text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                            </div>
                        </div>
                    </template>

                    {{-- ── Footer settings ── --}}
                    <template x-if="selectedBlock()?.type === 'footer'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Teks (kosong = cap auto)</label>
                                <input type="text" x-model="selectedBlock().settings.text"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Penjajaran</label>
                                <select x-model="selectedBlock().settings.align"
                                    class="w-full text-xs rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1.5">
                                    <option value="left">Kiri</option>
                                    <option value="center">Tengah</option>
                                    <option value="right">Kanan</option>
                                </select>
                            </div>
                        </div>
                    </template>

                    {{-- Generic fallback: show type name only --}}
                    <template x-if="!['letterhead','title','meta','attendees','agenda','richtext','action_tag','signature','divider','footer'].includes(selectedBlock()?.type)">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Blok ini tidak mempunyai tetapan tambahan.</p>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Preview modal — teleported to <body> so it sits above the fixed sidebar --}}
    <template x-teleport="body">
        <div x-show="showPreview" x-cloak
            class="fixed inset-0 flex items-center justify-center bg-black/60" style="z-index:9999"
            @click.self="showPreview = false">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl flex flex-col overflow-hidden" style="width:calc(100vw - 32px);height:calc(100vh - 32px)">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-slate-700 shrink-0">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">Pratonton Dokumen</h3>
                    <div class="flex items-center gap-2">
                        <select x-model="previewLanguage" @change="loadPreview()"
                            class="text-xs rounded border border-gray-300 dark:border-slate-600 dark:bg-slate-800 dark:text-white px-2 py-1">
                            <option value="ms">Bahasa Melayu</option>
                            <option value="en">English</option>
                        </select>
                        <button @click="showPreview = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-hidden relative">
                    {{-- Hidden form: POSTs blocks JSON into the iframe so we bypass URL-length limits --}}
                    <form x-ref="previewForm" method="POST" target="previewFrame"
                        :action="previewUrl" class="hidden">
                        @csrf
                        <input type="hidden" name="language" x-model="previewLanguage">
                        <input type="hidden" name="blocks_json" x-ref="blocksJsonInput">
                        <input type="hidden" name="mom_id" :value="sampleMomId ?? ''">
                    </form>
                    <iframe name="previewFrame" x-ref="previewFrame" class="w-full h-full border-0 bg-white"></iframe>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('templateBuilder', (config) => ({
        templateId:      config.templateId,
        blocks:          config.blocks.length ? config.blocks : [],
        primaryColor:    config.primaryColor,
        fontFamily:      config.fontFamily,
        labels:          config.labels,
        pageSetup:       config.pageSetup,
        glossary:        config.glossary,
        saveUrl:         config.saveUrl,
        previewUrl:      config.previewUrl,
        logoUrl:         null,
        blockTypes:      config.blockTypes,
        sampleMomId:     config.sampleMomId,
        previewLanguage: 'ms',
        selectedIndex:   null,
        showPreview:     false,
        saving:          false,
        saved:           false,
        logoUploading:   false,
        linesText:       '',
        sortable:        null,

        init() {
            this.$nextTick(() => this.initSortable());
        },

        initSortable() {
            const canvas = document.getElementById('block-canvas');
            if (!canvas || !window.Sortable) return;
            this.sortable = Sortable.create(canvas, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'opacity-40',
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;
                    const moved = this.blocks.splice(evt.oldIndex, 1)[0];
                    this.blocks.splice(evt.newIndex, 0, moved);
                    if (this.selectedIndex === evt.oldIndex) {
                        this.selectedIndex = evt.newIndex;
                    }
                    this.saved = false;
                },
            });
        },

        addBlock(type) {
            const id = 'b-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            const defaults = {
                letterhead:   { align: 'center', show_rule: true, lines: ['@{{organization.name}}'] },
                title:        { text: '@{{title}}', align: 'center' },
                meta:         { separator: ':', fields: [] },
                attendees:    { groups: ['present', 'also_present', 'absent'] },
                agenda:       { source: 'topics', extraction_types: ['summary', 'topics', 'decisions', 'risks'] },
                action_items: {},
                richtext:     { content: '', source: '' },
                action_tag:   { tag: 'Makluman', align: 'right' },
                table:        { headers: [], rows: [] },
                image:        { path: '', align: 'center' },
                signature:    { columns: [
                    { label: 'prepared_by', name: '@{{prepared_by}}', position: '', show_date: true },
                    { label: 'approved_by', name: '@{{chair.name}}',  position: '', show_date: true },
                ]},
                divider:      { color: '#e5e7eb', thickness: 1 },
                pagebreak:    {},
                footer:       { align: 'center', text: '' },
            };
            this.blocks.push({ id, type, settings: defaults[type] ?? {} });
            this.selectedIndex = this.blocks.length - 1;
            this.saved = false;

            this.$nextTick(() => {
                const canvas = document.getElementById('block-canvas');
                const last = canvas?.lastElementChild;
                last?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },

        removeBlock(index) {
            this.blocks.splice(index, 1);
            if (this.selectedIndex === index) this.selectedIndex = null;
            else if (this.selectedIndex > index) this.selectedIndex--;
            this.saved = false;
        },

        moveBlock(index, dir) {
            const to = index + dir;
            if (to < 0 || to >= this.blocks.length) return;
            [this.blocks[index], this.blocks[to]] = [this.blocks[to], this.blocks[index]];
            this.selectedIndex = to;
            this.saved = false;
        },

        selectBlock(index) {
            this.selectedIndex = index;
        },

        selectedBlock() {
            return this.selectedIndex !== null ? this.blocks[this.selectedIndex] : null;
        },

        blockMeta(type) {
            return this.blockTypes.find(b => b.type === type);
        },

        blockSummary(block) {
            const s = block.settings ?? {};
            switch (block.type) {
                case 'letterhead':   return (s.lines ?? []).join(' • ') || 'Letterhead';
                case 'title':        return s.text || '@{{title}}';
                case 'meta':         return 'Tarikh, Masa, Tempat';
                case 'attendees':    return (s.groups ?? []).join(', ');
                case 'agenda':       return 'Topik + ' + (s.extraction_types ?? []).join(', ');
                case 'action_items': return 'Jadual tindakan susulan';
                case 'richtext':     return s.source === 'manual_notes' ? 'Nota manual' : (s.content ?? '').substring(0, 50);
                case 'action_tag':   return s.tag || 'Makluman';
                case 'signature':    return (s.columns ?? []).map(c => c.name).join(' / ');
                case 'divider':      return 'Garisan pemisah';
                case 'pagebreak':    return 'Pecahan halaman';
                case 'footer':       return s.text || 'Cap auto';
                default:             return block.type;
            }
        },

        toggleGroup(g) {
            const block = this.selectedBlock();
            if (!block) return;
            block.settings.groups = block.settings.groups ?? ['present', 'also_present', 'absent'];
            const idx = block.settings.groups.indexOf(g);
            if (idx >= 0) block.settings.groups.splice(idx, 1);
            else block.settings.groups.push(g);
            this.saved = false;
        },

        toggleExtraction(t) {
            const block = this.selectedBlock();
            if (!block) return;
            block.settings.extraction_types = block.settings.extraction_types ?? ['summary', 'topics', 'decisions', 'risks'];
            const idx = block.settings.extraction_types.indexOf(t);
            if (idx >= 0) block.settings.extraction_types.splice(idx, 1);
            else block.settings.extraction_types.push(t);
            this.saved = false;
        },

        async save() {
            this.saving = true;
            this.saved = false;
            try {
                const res = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        blocks:        this.blocks,
                        labels:        this.labels,
                        page_setup:    { ...this.pageSetup, font: this.fontFamily },
                        glossary:      this.glossary,
                        primary_color: this.primaryColor,
                        font_family:   this.fontFamily,
                    }),
                });
                if (!res.ok) throw new Error('Save failed');
                this.saved = true;
                setTimeout(() => this.saved = false, 3000);
            } finally {
                this.saving = false;
            }
        },

        openPreview() {
            this.showPreview = true;
            this.$nextTick(() => this.loadPreview());
        },

        loadPreview() {
            const form = this.$refs.previewForm;
            const input = this.$refs.blocksJsonInput;
            if (!form || !input) return;
            input.value = JSON.stringify(this.blocks);
            form.submit();
        },

        async uploadLogo(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.logoUploading = true;
            const form = new FormData();
            form.append('logo', file);
            try {
                const res = await fetch('{{ route('settings.export-templates.logo', $template) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: form,
                });
                const json = await res.json();
                if (json.ok) {
                    this.logoUrl = json.logo_url;
                    // Also inject logo_path into any letterhead block
                    this.blocks.forEach(b => {
                        if (b.type === 'letterhead') b.settings.logo_path = json.logo_path;
                    });
                    this.saved = false;
                }
            } finally {
                this.logoUploading = false;
            }
        },
    }));
});
</script>
@endsection
