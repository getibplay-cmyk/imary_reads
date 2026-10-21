@props(['name'=>'book'])
<svg {{ $attributes->merge(['class'=>'icon','viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>'1.6','stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true']) }}>
@switch($name)
@case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>@break
@case('heart')<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>@break
@case('pen')<path d="m15 5 4 4M4 20l4-1 12-12a2.8 2.8 0 0 0-4-4L4 15zM4 20h16"/>@break
@case('users')<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3m1-18a4 4 0 0 1 0 8m3 10v-3a6 6 0 0 0-3-5"/>@break
@case('moon')<path d="M21 13A9 9 0 0 1 11 3a9 9 0 1 0 10 10Z"/>@break
@case('menu')<path d="M4 5h16M4 12h16M4 19h16"/>@break
@case('arrow')<path d="m14 5-7 7 7 7M7 12h15"/>@break
@case('star')<path d="m12 2 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>@break
@case('bookmark')<path d="M6 3h12v18l-6-4-6 4z"/>@break
@case('shield')<path d="m12 2 9 4v6c0 6-9 10-9 10S3 18 3 12V6zM8 12l3 3 5-6"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M9 21h6"/>@break
@case('settings')<path d="m9 2 6 0 1 3 3 1 3 5-2 2v3l-4 4-3-1-2 2-5-3-1-3-3-1V9l3-1 1-3z"/><circle cx="12" cy="12" r="3"/>@break
@case('home')<path d="m3 11 9-8 9 8M5 10v11h5v-7h4v7h5V10"/>@break
@case('download')<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>@break
@case('plus')<path d="M12 4v16M4 12h16"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
@case('fullscreen')<path d="M3 8V3h5m8 0h5v5m0 8v5h-5M8 21H3v-5"/>@break
@case('close')<path d="m5 5 14 14M5 19 19 5"/>@break
@default<path d="M12 5C8 2 4 3 2 4v16c4-1 7 0 10 2 3-2 6-3 10-2V4c-4-1-7-2-10 1v17"/>
@endswitch
</svg>
