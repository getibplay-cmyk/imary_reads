@props(['title'=>null,'reader'=>false])
<!doctype html><html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}" data-theme="dark"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · ' : '' }}Imary Reads</title><meta name="description" content="{{ __('ui.hero_description') }}"><meta name="theme-color" content="#050d20"><link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">@vite(['resources/css/app.css','resources/js/app.js'])
</head><body class="{{ $reader?'reader-body':'' }}" data-labels="{{ json_encode(collect(__('ui'))->only(['search_empty','search_error','saving','saved','unsaved','save_error','conflict','page','of','leave_warning','error','content'])->all(),JSON_UNESCAPED_UNICODE) }}">
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="site-header"><div class="header-inner">
<a class="brand" href="{{ route('home') }}" aria-label="Imary Reads"><img src="{{ asset('images/logo.svg') }}" alt="" width="46" height="46"><span><b>Imary Reads</b><small>{{ __('ui.brand_tagline') }}</small></span></a>
<button class="icon-button mobile-menu" data-menu-toggle aria-controls="main-nav" aria-expanded="false" aria-label="{{ __('ui.menu') }}"><x-icon name="menu"/></button>
<nav id="main-nav" class="main-nav" aria-label="{{ __('ui.menu') }}">
@foreach(['home','library','categories','writers','blog'] as $nav)<a href="{{ route($nav) }}" @class(['active'=>request()->routeIs($nav)])>{{ __('ui.'.$nav) }}</a>@endforeach
</nav>
<form class="quick-search" action="{{ route('library') }}" method="get" role="search"><x-icon name="search"/><input name="q" placeholder="{{ __('ui.search') }}" aria-label="{{ __('ui.search') }}" data-search-url="{{ route('search') }}" autocomplete="off" maxlength="120" aria-autocomplete="list" aria-controls="search-results" aria-expanded="false"><div id="search-results" class="search-results" role="listbox" hidden></div><span class="sr-only" data-search-status aria-live="polite"></span></form>
<div class="header-actions"><button class="icon-button" data-theme-toggle aria-label="{{ __('ui.theme') }}"><x-icon name="moon"/></button>
<form action="{{ route('locale') }}" method="post" class="locale-form">@csrf<label class="sr-only" for="locale-switch">{{ __('ui.language') }}</label><select name="locale" id="locale-switch" data-autosubmit>@foreach(config('imary.locales') as $code=>$name)<option value="{{ $code }}" @selected(app()->getLocale()===$code)>{{ $name }}</option>@endforeach</select><noscript><button>{{ __('ui.save') }}</button></noscript></form>
@guest<a class="button ghost login-link" href="{{ route('login') }}">{{ __('ui.login') }}</a><a class="button small" href="{{ route('register') }}">{{ __('ui.register') }}</a>@else
<a class="icon-button" href="{{ route('notifications') }}" aria-label="{{ __('ui.notifications') }}"><x-icon name="bell"/></a><a class="avatar" href="{{ route('account') }}" aria-label="{{ __('ui.my_library') }}">{{ mb_substr(auth()->user()->name,0,1) }}</a>
@endguest</div></div></header>
@if($siteAnnouncement??null)<div class="notice container" role="status">{{ $siteAnnouncement }}</div>@endif
@if(session('status'))<div class="toast" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error container" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<main id="main">{{ $slot }}</main>
@if(!$reader)<footer class="site-footer container"><a class="brand" href="{{ route('home') }}"><img src="{{ asset('images/logo.svg') }}" width="40" height="40" alt=""><span><b>Imary Reads</b><small>{{ __('ui.footer_tagline') }}</small></span></a><nav>@foreach(['terms','privacy','copyright'] as $page)<a href="{{ route('legal',$page) }}">{{ __('ui.'.$page) }}</a>@endforeach<a href="{{ route('writers') }}">{{ __('ui.writers') }}</a></nav><small>© {{ date('Y') }} Imary Reads</small></footer>@endif
<div class="live-toast" role="status" aria-live="polite" hidden></div></body></html>
