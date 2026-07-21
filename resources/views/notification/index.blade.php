@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-4 sm:space-y-6 animate__animated animate__fadeIn px-1 sm:px-0">

    {{-- ── Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}"
               class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-500
                      hover:text-indigo-600 hover:bg-slate-50 shadow-sm transition-all flex-shrink-0">
                <i data-lucide="arrow-left" class="w-4 h-4 sm:w-5 sm:h-5"></i>
            </a>
            <div>
                <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-800">Notifications</h2>
                <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest">System Alerts &amp; Logs</p>
            </div>
        </div>

        {{-- Mark all as read (scoped to current tab) --}}
        @if($totalUnread > 0)
            <form action="{{ route('notification.read-all') }}?type={{ $type }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <button type="submit"
                        class="w-full sm:w-auto justify-center bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-100
                               px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5
                               active:scale-95 shadow-sm whitespace-nowrap">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    Tandai semua dibaca
                </button>
            </form>
        @endif
    </div>

    {{-- ── Filter Tabs ── --}}
    <div class="flex gap-1 sm:gap-2 bg-slate-100/80 p-1 rounded-2xl w-full sm:w-fit overflow-x-auto no-scrollbar">
        @php
            $tabs = [
                ['key' => 'all',      'label' => 'Semua',       'count' => $totalUnread,    'icon' => 'bell'],
                ['key' => 'pump',     'label' => 'Pompa',       'count' => $pumpUnread,     'icon' => 'cog'],
                ['key' => 'rainfall', 'label' => 'Curah Hujan', 'count' => $rainfallUnread, 'icon' => 'cloud-rain'],
            ];
        @endphp

        @foreach($tabs as $tab)
            <a href="{{ route('notification.index') }}?type={{ $tab['key'] }}"
               class="flex-1 sm:flex-none justify-center flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-xl text-[11px] sm:text-xs font-bold transition-all whitespace-nowrap
                      {{ $type === $tab['key']
                            ? 'bg-white text-slate-800 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700' }}">
                <i data-lucide="{{ $tab['icon'] }}" class="w-3.5 h-3.5 flex-shrink-0"></i>
                <span>{{ $tab['label'] }}</span>
                @if($tab['count'] > 0)
                    <span class="inline-flex items-center justify-center min-w-[1.125rem] h-4 px-1 rounded-full text-[9px] font-black leading-none
                                 {{ $type === $tab['key'] ? 'bg-indigo-500 text-white' : 'bg-slate-300 text-slate-600' }}">
                        {{ $tab['count'] > 9 ? '9+' : $tab['count'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- ── Empty State ── --}}
    @if($notifications->isEmpty())
        <div class="bg-white rounded-[2rem] sm:rounded-[2.5rem] p-8 sm:p-12 text-center border border-slate-100 shadow-sm">
            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                <i data-lucide="bell-off" class="w-8 h-8 sm:w-10 sm:h-10"></i>
            </div>
            <h3 class="text-base sm:text-lg font-bold text-slate-700">Tidak Ada Notifikasi</h3>
            <p class="text-slate-400 text-xs sm:text-sm mt-1 max-w-xs mx-auto">
                Belum ada notifikasi
                @if($type === 'pump') pompa @elseif($type === 'rainfall') curah hujan @endif
                yang tersimpan.
            </p>
        </div>

    {{-- ── Notification Cards ── --}}
    @else
        <div class="space-y-2.5 sm:space-y-3">
            @foreach($notifications as $notification)
                @php
                    $isPump     = $notification->type === 'pump';
                    $isRainfall = $notification->type === 'rainfall';
                    $isUnread   = !$notification->is_read;

                    if ($isPump) {
                        $iconName     = 'cog';
                        $iconBg       = $isUnread ? 'bg-red-50'    : 'bg-slate-50';
                        $iconColor    = $isUnread ? 'text-red-500'  : 'text-slate-400';
                        $ringClass    = $isUnread ? 'ring-1 ring-red-400/20 shadow-md sm:shadow-lg shadow-red-500/5' : 'shadow-sm';
                        $dotColor     = 'bg-red-500';
                        $typeBadge    = 'Pompa';
                        $typeBadgeCss = 'bg-red-50 text-red-600 border border-red-100';
                    } elseif ($isRainfall) {
                        $iconName     = 'cloud-rain';
                        $iconBg       = $isUnread ? 'bg-blue-50'   : 'bg-slate-50';
                        $iconColor    = $isUnread ? 'text-blue-500' : 'text-slate-400';
                        $ringClass    = $isUnread ? 'ring-1 ring-blue-400/20 shadow-md sm:shadow-lg shadow-blue-500/5' : 'shadow-sm';
                        $dotColor     = 'bg-blue-500';
                        $typeBadge    = 'Curah Hujan';
                        $typeBadgeCss = 'bg-blue-50 text-blue-600 border border-blue-100';
                    } else {
                        $iconName     = 'bell';
                        $iconBg       = $isUnread ? 'bg-indigo-50'    : 'bg-slate-50';
                        $iconColor    = $isUnread ? 'text-indigo-500'  : 'text-slate-400';
                        $ringClass    = $isUnread ? 'ring-1 ring-indigo-400/20 shadow-md sm:shadow-lg shadow-indigo-500/5' : 'shadow-sm';
                        $dotColor     = 'bg-indigo-500';
                        $typeBadge    = 'Sistem';
                        $typeBadgeCss = 'bg-indigo-50 text-indigo-600 border border-indigo-100';
                    }
                @endphp

                <div class="relative bg-white border border-slate-100 {{ $ringClass }} rounded-2xl sm:rounded-[2rem] p-3.5 sm:p-5
                            flex gap-3 sm:gap-4 transition-all hover:scale-[1.005] sm:hover:scale-[1.01] group">

                    {{-- Unread pulse dot --}}
                    @if($isUnread)
                        <span class="absolute top-3.5 right-3.5 sm:top-5 sm:right-5 w-2 h-2 sm:w-2.5 sm:h-2.5 {{ $dotColor }} rounded-full animate-pulse"></span>
                    @endif

                    {{-- Icon --}}
                    <div class="w-10 h-10 sm:w-12 sm:h-12 {{ $iconBg }} {{ $iconColor }} rounded-xl sm:rounded-2xl flex items-center justify-center
                                flex-shrink-0 shadow-inner transition-transform group-hover:scale-105 sm:group-hover:scale-110">
                        <i data-lucide="{{ $iconName }}" class="w-5 h-5 sm:w-6 sm:h-6
                           {{ $isPump && $isUnread ? 'animate-spin' : '' }}"
                           style="{{ $isPump && $isUnread ? 'animation-duration:4s;' : '' }}">
                        </i>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0 pr-2 sm:pr-4">
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap mb-1">
                            <h4 class="text-xs sm:text-sm font-black text-slate-800 tracking-tight leading-snug">
                                {{ $notification->title }}
                            </h4>
                            <span class="text-[8px] sm:text-[9px] font-bold px-1.5 sm:px-2 py-0.5 rounded-full {{ $typeBadgeCss }} uppercase tracking-wide">
                                {{ $typeBadge }}
                            </span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-slate-500 font-medium leading-relaxed mb-2 sm:mb-2.5">
                            {{ $notification->body }}
                        </p>
                        <div class="flex items-center gap-3 sm:gap-4 flex-wrap">
                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                <i data-lucide="clock" class="w-3 h-3"></i>
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                            @if($isUnread)
                                <form action="{{ route('notification.read', $notification) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="text-[9px] sm:text-[10px] font-bold text-indigo-500 hover:text-indigo-700
                                                   flex items-center gap-1 transition-all active:scale-95">
                                        <i data-lucide="check" class="w-3 h-3"></i>
                                        Tandai dibaca
                                    </button>
                                </form>
                            @else
                                <span class="text-[9px] sm:text-[10px] font-bold text-slate-300 flex items-center gap-1">
                                    <i data-lucide="check-check" class="w-3 h-3"></i>
                                    Sudah dibaca
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="pt-2">
            {{ $notifications->links() }}
        </div>
    @endif

</div>
@endsection

