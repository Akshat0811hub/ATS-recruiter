@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'briefcase' => 'M9 6V4h6v2 M3 6h18v14H3z M3 11c6 4 12 4 18 0 M10 12h4',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'building' => 'M4 21V3h12v18 M16 9h4v12 M8 7h4 M8 11h4 M8 15h4 M8 21v-3h4v3',
        'calendar' => 'M3 5h18v16H3z M16 3v4 M8 3v4 M3 11h18 M8 15h2 M14 15h2',
        'check' => 'M9 11l3 3L22 4 M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
        'pipeline' => 'M3 4h5v16H3z M10 4h5v10h-5z M17 4h4v13h-4z',
        'document' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h5',
        'send' => 'M22 2L9 15 M22 2l-7 20-6-7-7-6z',
        'chart' => 'M3 3v18h18 M7 16v-5 M12 16V7 M17 16V4',
        'clock' => 'M12 8v4l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'shield' => 'M12 3l8 4v5c0 5-8 9-8 9s-8-4-8-9V7z M8 12l3 3 5-6',
        'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6',
        'search' => 'M21 21l-6-6 M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
        'plus' => 'M12 5v14 M5 12h14',
        'chevron' => 'M6 9l6 6 6-6',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'close' => 'M6 6l12 12 M6 18L18 6',
        'panel' => 'M3 4h18v16H3z M9 4v16',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
        'logout' => 'M9 21H4V3h5 M16 17l5-5-5-5 M21 12H9',
        'mail' => 'M3 5h18v14H3z M3 5l9 7 9-7',
        'checkmark' => 'M5 12l4 4L19 6',
    ];
@endphp
<svg {{ $attributes->class(['size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}" /></svg>
