@props(['admin'=>false,'title'=>''])
<x-layout :title="$title"><div class="studio container"><aside class="studio-sidebar"><span class="eyebrow">IMary / {{ $admin?'ADMIN':'STUDIO' }}</span><h2>{{ $admin?__('ui.admin'):__('ui.writer_dashboard') }}</h2>
@php($links=$admin?['admin.index'=>['admin','grid'],'admin.users'=>['users','users'],'admin.taxonomy'=>['categories','book'],'admin.posts'=>['posts','pen'],'admin.files'=>['files','download'],'admin.audit'=>['audit','shield'],'admin.settings'=>['settings','settings']]:['writer.index'=>['my_works','book'],'writer.create'=>['add_work','plus'],'writer.comments'=>['comments','pen'],'writer.followers'=>['followers','users'],'account'=>['my_library','heart'],'account.settings'=>['settings','settings']])
@foreach($links as $route=>[$label,$icon])<a href="{{ route($route) }}" @class(['active'=>request()->routeIs($route)])><x-icon :name="$icon"/>{{ __('ui.'.$label) }}</a>@endforeach
@if(auth()->user()->isStaff()&&!$admin)<a href="{{ route('admin.index') }}"><x-icon name="shield"/>{{ __('ui.admin') }}</a>@endif
<a href="{{ route('home') }}"><x-icon name="home"/>{{ __('ui.home') }}</a>
</aside><section class="studio-content"><h1>{{ $title }}</h1>{{ $slot }}</section></div></x-layout>
