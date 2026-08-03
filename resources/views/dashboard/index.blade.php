@extends('layouts.app')

@section('content')
<div class="space-y-6 animate__animated animate__fadeIn">
    
    <!-- Header Section -->
    <div class="flex flex-col gap-1">
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-800">
            Welcome to Dashboard
        </h2>
        <div class="flex items-center gap-2">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                System Live • <span id="last-updated-text">{{ $latestData ? $latestData->created_at->diffForHumans() : 'No data' }}</span>
            </p>
        </div>
    </div>

    @if(!$latestData)
    <!-- No Data State -->
    <div class="bg-white rounded-[2.5rem] p-12 text-center border border-slate-100 shadow-sm">
        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
            <i data-lucide="database-zap" class="w-10 h-10"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-700">No Sensor Data Yet</h3>
        <p class="text-slate-400 text-sm mt-1 max-w-xs mx-auto">Connecting sensors... data will appear here automatically once transmitted.</p>
    </div>
    @else
    @php
        $status = $latestData->status;
        $videoFile = 'norain.mp4';
        $imageFile = 'norain.png';
        if ($status === 'Very Light Rain') {
            $videoFile = 'verylightrain.mp4';
            $imageFile = 'verylightrain.png';
        } elseif ($status === 'Light Rain') {
            $videoFile = 'lightrain.mp4';
            $imageFile = 'lightrain.png';
        } elseif ($status === 'Moderate Rain') {
            $videoFile = 'moderaterain.mp4';
            $imageFile = 'moderaterain.png';
        } elseif ($status === 'Heavy Rain') {
            $videoFile = 'heavyrain.mp4';
            $imageFile = 'heavyrain.png';
        } elseif ($status === 'Very Heavy Rain') {
            $videoFile = 'veryheavyrain.mp4';
            $imageFile = 'veryheavyrain.png';
        }
    @endphp

    <!-- Main Status Banner -->
    <a href="{{ route('monitoring.rainfall') }}" class="block relative overflow-hidden rounded-[2.5rem] group hover:scale-[1.01] hover:shadow-xl hover:shadow-primary-600/20 active:scale-[0.99] transition-all duration-300">
        <!-- Blurred Image Fallback (shown while video loads) -->
        <img
            id="rainfall-bg-image"
            src="{{ asset('images/' . $imageFile) }}"
            data-base-url="{{ asset('images') }}/"
            alt="rainfall background"
            class="absolute inset-0 w-full h-full object-cover rounded-[2.5rem] z-0"
            style="object-position: 65% 15%; filter: blur(8px); transform: scale(1.15);"
        />
        <!-- Video (fades in once ready, sits on top of image) -->
        <video id="rainfall-video" data-base-url="{{ asset('video') }}/" autoplay muted loop playsinline
            class="absolute inset-0 w-full h-full object-cover rounded-[2.5rem] z-[1] opacity-0 transition-opacity duration-700"
            style="object-position: 65% 15%;"
            src="{{ asset('video/' . $videoFile) }}">
        </video>
        <div class="absolute inset-0 bg-primary-600/40 rounded-[2.5rem] z-10"></div>

        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
        
        <div class="relative z-20 p-8 text-white flex flex-col md:flex-row md:items-center justify-between gap-6 pb-12 md:pb-8">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-3xl flex items-center justify-center shadow-inner">
                    <i data-lucide="cloud-rain" class="w-10 h-10"></i>
                </div>
                <div>
                    <p class="text-primary-100 text-[10px] font-bold uppercase tracking-widest mb-2 leading-none">Rainfall</p>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-baseline gap-2">
                            <span class="text-5xl font-black tracking-tight" id="rainfall-value">{{ number_format($latestData->rainfall_hourly, 2) }}</span>
                            <span class="text-primary-200 font-bold text-sm uppercase tracking-wider">mm/hour</span>
                        </div>
                        
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 backdrop-blur-md rounded-xl border border-white/10 text-white shadow-inner w-fit sm:ml-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-300 animate-pulse"></span>
                            <span id="rainfall-minute-value" class="text-xs font-bold tabular-nums">{{ number_format($latestData->rainfall, 2) }}</span>
                            <span class="text-[9px] font-bold text-primary-200 uppercase tracking-wider">mm/minute</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl px-6 py-4 flex flex-col items-center flex-shrink-0">
                <p class="text-[10px] font-bold text-primary-100 uppercase tracking-widest mb-1">Status</p>
                @php
                    $r = $latestData->rainfall_hourly;
                    if ($r < 1) {
                        $dotColor = 'bg-green-400';
                        $shadowColor = 'rgba(74,222,128,0.5)';
                    } elseif ($r <= 5) {
                        $dotColor = 'bg-green-400';
                        $shadowColor = 'rgba(74,222,128,0.5)';
                    } elseif ($r <= 10) {
                        $dotColor = 'bg-amber-400';
                        $shadowColor = 'rgba(251,191,36,0.5)';
                    } else {
                        $dotColor = 'bg-red-400';
                        $shadowColor = 'rgba(248,113,113,0.5)';
                    }
                @endphp
                <div class="flex items-center gap-2">
                    <span id="status-dot" class="w-3 h-3 rounded-full {{ $dotColor }}" style="box-shadow: 0 0 12px {{ $shadowColor }}"></span>
                    <span class="text-xl font-extrabold uppercase tracking-tight" id="status-value">{{ $latestData->status }}</span>
                </div>
            </div>
        </div>

        <!-- Click Indicator Hint -->
        <div class="absolute bottom-4 right-8 z-20 bg-white/10 border border-white/10 backdrop-blur-md px-3 py-1 rounded-full text-white/85 flex items-center gap-1.5 opacity-80 group-hover:opacity-100 group-hover:bg-white group-hover:text-primary-600 transition-all duration-300 shadow-sm">
            <span class="text-[9px] font-bold uppercase tracking-widest">View Details</span>
            <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
        </div>
    </a>

    <!-- Core Metrics Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Temp -->
        <a href="{{ route('monitoring.temperature') }}" class="relative block bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-lg hover:scale-[1.03] hover:-translate-y-1 active:scale-[0.97] hover:border-orange-300 hover:shadow-orange-100/40 transition-all duration-300 group">
            <!-- Clickable Corner Action Badge -->
            <div class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400 group-hover:bg-orange-500 group-hover:text-white group-hover:border-transparent group-hover:scale-110 shadow-sm transition-all duration-300">
                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
            </div>
            <div class="w-10 h-10 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i data-lucide="thermometer" class="w-5 h-5"></i>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Temperature</p>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-black text-slate-800" id="temperature-value">{{ $latestData->temperature }}</span>
                <span class="text-xs font-bold text-slate-500">°C</span>
            </div>
        </a>

        <!-- Humidity -->
        <a href="{{ route('monitoring.humidity') }}" class="relative block bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-lg hover:scale-[1.03] hover:-translate-y-1 active:scale-[0.97] hover:border-cyan-300 hover:shadow-cyan-100/40 transition-all duration-300 group">
            <!-- Clickable Corner Action Badge -->
            <div class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400 group-hover:bg-cyan-500 group-hover:text-white group-hover:border-transparent group-hover:scale-110 shadow-sm transition-all duration-300">
                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
            </div>
            <div class="w-10 h-10 bg-cyan-50 text-cyan-500 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i data-lucide="droplets" class="w-5 h-5"></i>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Humidity</p>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-black text-slate-800" id="humidity-value">{{ $latestData->humidity }}</span>
                <span class="text-xs font-bold text-slate-500">%</span>
            </div>
        </a>

        <!-- Water Level -->
        <a href="{{ route('monitoring.water_level') }}" class="relative block bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-lg hover:scale-[1.03] hover:-translate-y-1 active:scale-[0.97] hover:border-cyan-300 hover:shadow-cyan-100/40 transition-all duration-300 group">
            <!-- Clickable Corner Action Badge -->
            <div class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400 group-hover:bg-cyan-500 group-hover:text-white group-hover:border-transparent group-hover:scale-110 shadow-sm transition-all duration-300">
                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
            </div>
            <div class="w-10 h-10 bg-cyan-50 text-cyan-500 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i data-lucide="waves" class="w-5 h-5"></i>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Water Level</p>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-black text-slate-800" id="water-level-value">{{ $latestData->water_level }}</span>
                <span class="text-xs font-bold text-slate-500">m</span>
            </div>
            
            @php
                $wl = floatval($latestData->water_level);
                $isAman = ($wl > 0.82);
                $isWaspada = ($wl > 0.62 && $wl <= 0.82);
                $isSiaga = ($wl >= 0.40 && $wl <= 0.62);
                $isBanjir = ($wl < 0.40);
            @endphp

            <!-- Status Badges Grid -->
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-1 mt-3">
                <!-- AMAN Badge -->
                <div id="wl-badge-aman" class="text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal
                    {{ $isAman 
                        ? 'bg-[#22C55E] text-white shadow-[0_0_12px_rgba(34,197,94,0.6)] scale-[1.03] border border-[#22C55E]' 
                        : 'bg-transparent text-[#22C55E] border border-green-500/20 opacity-50' 
                    }}">
                    AMAN
                </div>
                <!-- WASPADA Badge -->
                <div id="wl-badge-waspada" class="text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal
                    {{ $isWaspada 
                        ? 'bg-[#F59E0B] text-white shadow-[0_0_12px_rgba(245,158,11,0.6)] scale-[1.03] border border-[#F59E0B]' 
                        : 'bg-transparent text-[#F59E0B] border border-amber-500/20 opacity-50' 
                    }}">
                    WASPADA
                </div>
                <!-- SIAGA Badge -->
                <div id="wl-badge-siaga" class="text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal
                    {{ $isSiaga 
                        ? 'bg-[#F97316] text-white shadow-[0_0_12px_rgba(249,115,22,0.6)] scale-[1.03] border border-[#F97316]' 
                        : 'bg-transparent text-[#F97316] border border-orange-500/20 opacity-50' 
                    }}">
                    SIAGA
                </div>
                <!-- BANJIR Badge -->
                <div id="wl-badge-banjir" class="text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal
                    {{ $isBanjir 
                        ? 'bg-[#EF4444] text-white shadow-[0_0_12px_rgba(239,68,68,0.6)] scale-[1.03] border border-[#EF4444]' 
                        : 'bg-transparent text-[#EF4444] border border-red-500/20 opacity-50' 
                    }}">
                    BANJIR
                </div>
            </div>
        </a>

        <!-- Lux -->
        <a href="{{ route('monitoring.lux') }}" class="relative block bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-lg hover:scale-[1.03] hover:-translate-y-1 active:scale-[0.97] hover:border-amber-300 hover:shadow-amber-100/40 transition-all duration-300 group">
            <!-- Clickable Corner Action Badge -->
            <div class="absolute top-4 right-4 w-7 h-7 rounded-full bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400 group-hover:bg-amber-500 group-hover:text-white group-hover:border-transparent group-hover:scale-110 shadow-sm transition-all duration-300">
                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
            </div>
            <div class="w-10 h-10 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <i data-lucide="sun" class="w-5 h-5"></i>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Light (Lux)</p>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-black text-slate-800" id="lux-value">{{ number_format($latestData->lux, 0) }}</span>
            </div>
        </a>
    </div>

    <!-- System Health Section -->
    <div class="flex flex-col gap-4">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest px-2">System Health & Power</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Solar Energy Card -->
            <a href="{{ route('monitoring.solar_panel') }}" class="relative bg-white pl-6 pr-16 py-5 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between hover:shadow-lg hover:scale-[1.02] hover:-translate-y-1 active:scale-[0.97] hover:border-emerald-300 hover:shadow-emerald-100/40 transition-all duration-300 group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="zap" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-slate-800 tracking-tight">Solar Panel</h4>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Energy Input</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="flex items-baseline justify-end gap-1">
                        <span class="text-xl font-black text-slate-800" id="voltage-panel-value">{{ $latestData->voltage_panel }}</span>
                        <span class="text-[10px] font-bold text-slate-400">V</span>
                    </div>
                    <p class="text-[10px] font-bold text-emerald-500 uppercase"><span id="current-panel-value">{{ $latestData->current_panel }}</span> A</p>
                </div>
                <!-- Interactive Action Badge -->
                <div class="absolute right-4 w-8 h-8 rounded-full bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400 group-hover:bg-emerald-500 group-hover:text-white group-hover:border-transparent group-hover:scale-110 shadow-sm transition-all duration-300">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </div>
            </a>

            <!-- Battery Card -->
            <a href="{{ route('monitoring.battery') }}" class="relative bg-white pl-6 pr-16 py-5 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between hover:shadow-lg hover:scale-[1.02] hover:-translate-y-1 active:scale-[0.97] hover:border-indigo-300 hover:shadow-indigo-100/40 transition-all duration-300 group">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i data-lucide="battery" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-slate-800 tracking-tight">Battery</h4>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Storage Status</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="flex items-baseline justify-end gap-1">
                        <span class="text-xl font-black text-slate-800" id="voltage-battery-value">{{ $latestData->voltage_baterai }}</span>
                        <span class="text-[10px] font-bold text-slate-400">V</span>
                    </div>
                    <p class="text-[10px] font-bold text-indigo-500 uppercase"><span id="current-battery-value">{{ $latestData->current_baterai }}</span> A</p>
                </div>
                <!-- Interactive Action Badge -->
                <div class="absolute right-4 w-8 h-8 rounded-full bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-500 group-hover:text-white group-hover:border-transparent group-hover:scale-110 shadow-sm transition-all duration-300">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- Export Data Section -->
    <div class="bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-xl shadow-slate-200/50 flex flex-col md:flex-row items-center justify-between gap-6 transition-all">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center">
                <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
            </div>
            <div>
                <h4 class="text-base font-black text-slate-800 tracking-tight">Export Sensor Data</h4>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Download as Excel (.xlsx)</p>
            </div>
        </div>
        
        <form action="{{ route('dashboard.export') }}" method="GET" class="flex flex-col md:flex-row items-center gap-4 w-full md:w-auto">
            <div class="flex flex-col gap-1 w-full md:w-44">
                <label class="text-[9px] font-bold text-slate-400 uppercase ml-1">Start Date</label>
                <input type="date" name="start_date" id="export-start-date" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all">
            </div>
            <div class="flex flex-col gap-1 w-full md:w-44">
                <label class="text-[9px] font-bold text-slate-400 uppercase ml-1">End Date</label>
                <input type="date" name="end_date" id="export-end-date" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all">
            </div>
            <button type="submit" class="w-full md:w-auto mt-4 bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2.5 rounded-xl text-xs font-bold transition-all shadow-lg shadow-emerald-500/20 active:scale-95 flex items-center justify-center gap-2">
                <i data-lucide="download" class="w-4 h-4"></i>
                Download (.xlsx)
            </button>
        </form>
    </div>

    <!-- Extra Info Banner -->
    <div class="bg-slate-900 rounded-[2rem] p-6 text-white overflow-hidden relative">
        <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Internal Clock</p>
                <div class="flex items-center gap-2">
                    <i data-lucide="clock" class="w-4 h-4 text-primary-400"></i>
                    <span class="text-lg font-bold tracking-tight" id="timertc-value">{{ $latestData->timertc }}</span>
                </div>
            </div>
            
            <div class="flex items-center justify-between md:justify-start gap-8 px-2 md:px-8 md:border-x border-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <i data-lucide="activity" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <p class="text-[8px] font-bold text-slate-400 uppercase leading-none">Jitter</p>
                        <p class="text-sm font-black tracking-tight"><span id="jitter-value">{{ isset($latestData->jitter) ? number_format($latestData->jitter, 0, ',', '.') : '0' }}</span><span class="text-[9px] text-slate-500 ml-1">ms</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-cyan-500/20 flex items-center justify-center text-cyan-400">
                        <i data-lucide="wifi" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <p class="text-[8px] font-bold text-slate-400 uppercase leading-none">Delay</p>
                        <p class="text-sm font-black tracking-tight"><span id="delay-value">{{ isset($latestData->delay) ? number_format($latestData->delay, 0, ',', '.') : '0' }}</span><span class="text-[9px] text-slate-500 ml-1">ms</span></p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 w-full md:w-auto">
                <!-- Pump 1 -->
                <div class="bg-white/5 rounded-2xl px-4 py-2.5 flex items-center justify-between md:justify-start gap-4 border border-white/5">
                    <div id="pump-status-card" class="w-8 h-8 rounded-lg bg-{{ $latestData->status_pompa ? 'emerald' : 'slate' }}-500 flex items-center justify-center shadow-lg shadow-{{ $latestData->status_pompa ? 'emerald' : 'slate' }}-500/20">
                        <i id="pump-status-icon" data-lucide="cog" class="w-5 h-5 {{ $latestData->status_pompa ? 'animate-spin' : '' }}"></i>
                    </div>
                    <div>
                        <p class="text-[8px] font-bold text-slate-400 uppercase leading-none">Pump 1</p>
                        <p id="pump-status-text" class="text-xs font-black uppercase text-{{ $latestData->status_pompa ? 'emerald' : 'slate' }}-400">{{ $latestData->status_pompa ? 'Active' : 'Off' }}</p>
                    </div>
                </div>

                <!-- Pump 2 -->
                <div class="bg-white/5 rounded-2xl px-4 py-2.5 flex items-center justify-between md:justify-start gap-4 border border-white/5">
                    <div id="pump-status-card-2" class="w-8 h-8 rounded-lg bg-{{ $latestData->status_pompa2 ? 'emerald' : 'slate' }}-500 flex items-center justify-center shadow-lg shadow-{{ $latestData->status_pompa2 ? 'emerald' : 'slate' }}-500/20">
                        <i id="pump-status-icon-2" data-lucide="cog" class="w-5 h-5 {{ $latestData->status_pompa2 ? 'animate-spin' : '' }}"></i>
                    </div>
                    <div>
                        <p class="text-[8px] font-bold text-slate-400 uppercase leading-none">Pump 2</p>
                        <p id="pump-status-text-2" class="text-xs font-black uppercase text-{{ $latestData->status_pompa2 ? 'emerald' : 'slate' }}-400">{{ $latestData->status_pompa2 ? 'Active' : 'Off' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

<div id="toast-container" class="fixed bottom-20 md:bottom-6 right-6 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none"></div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Pump states initialized from current page load database data
        let lastPump1Status = {{ $latestData && $latestData->status_pompa ? 'true' : 'false' }};
        let lastPump2Status = {{ $latestData && $latestData->status_pompa2 ? 'true' : 'false' }};

        // Rainfall threshold state — track last known hourly value to detect crossing
        let lastRainfallHourly = {{ $latestData ? $latestData->rainfall_hourly : 0 }};

        // Trigger browser notification
        function triggerBrowserNotification(title, body) {
            if (!("Notification" in window)) return;
            
            if (Notification.permission === "granted") {
                new Notification(title, { body, icon: '/images/logosimocanobg.png' });
            }
        }

        // Show elegant custom toast notification
        function showToastNotification(title, message, type = 'default') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto bg-white/95 border border-slate-100 shadow-2xl rounded-2xl p-4 flex gap-3.5 items-start animate__animated animate__fadeInUp duration-300';
            toast.style.backdropFilter = 'blur(10px)';

            // Choose icon and color based on notification type
            let iconBg = 'bg-indigo-50';
            let iconColor = 'text-indigo-600';
            let iconName = 'bell';
            if (type === 'rainfall') {
                iconBg = 'bg-blue-50';
                iconColor = 'text-blue-600';
                iconName = 'cloud-rain';
            } else if (type === 'pump') {
                iconBg = 'bg-red-50';
                iconColor = 'text-red-600';
                iconName = 'cog';
            }
            
            toast.innerHTML = `
                <div class="w-10 h-10 ${iconBg} ${iconColor} rounded-xl flex items-center justify-center flex-shrink-0 shadow-inner">
                    <i data-lucide="${iconName}" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-black text-slate-800 tracking-tight leading-none mb-1">${title}</h4>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">${message}</p>
                </div>
                <button class="text-slate-400 hover:text-slate-600 p-0.5 rounded-lg hover:bg-slate-50 transition-all self-start">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            `;

            // Hook close button
            const closeBtn = toast.querySelector('button');
            closeBtn.addEventListener('click', () => {
                toast.classList.replace('animate__fadeInUp', 'animate__fadeOutDown');
                setTimeout(() => toast.remove(), 500);
            });

            container.appendChild(toast);
            
            // Auto-create icons for the toast
            if (window.lucide) {
                window.lucide.createIcons({
                    attrs: {
                        class: 'w-4 h-4'
                    },
                    nameAttr: 'data-lucide',
                    nodeList: toast.querySelectorAll('[data-lucide]')
                });
            }

            // Auto dismiss after 6 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.replace('animate__fadeInUp', 'animate__fadeOutDown');
                    setTimeout(() => toast.remove(), 500);
                }
            }, 6000);
        }

        // Request browser Notification permission on bell icon click
        const bellBtn = document.getElementById('header-bell-button');
        if (bellBtn) {
            bellBtn.addEventListener('click', () => {
                if ("Notification" in window) {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            showToastNotification('System Alert', 'Browser push notifications enabled!');
                        }
                    });
                }
            });
        }

        // Video Fade-In Logic (blurred image shown until video is ready)
        const video = document.getElementById('rainfall-video');
        const bgImage = document.getElementById('rainfall-bg-image');

        function showVideo() {
            if (video) {
                video.classList.add('opacity-100');
                video.classList.remove('opacity-0');
            }
        }

        if (video) {
            // If video is already ready, show immediately
            if (video.readyState >= 3) {
                showVideo();
            }
            video.addEventListener('canplay', showVideo);
            video.addEventListener('playing', showVideo);
        }

        if (window.Echo) {
            window.Echo.channel('sensor-data')
                .listen('.new-data', (e) => {
                    console.log('Real-time data received:', e.data);
                    
                    const data = e.data;
                                      // Calculate dynamic status text & dot style
                    const r = parseFloat(data.rainfall_hourly);
                    let statusText = 'No Rain';
                    let dotClass = 'bg-green-400';
                    let shadowColor = 'rgba(74,222,128,0.5)';
 
                    if (r <= 0) {
                        statusText = 'No Rain';
                        dotClass = 'bg-green-400';
                        shadowColor = 'rgba(74,222,128,0.5)';
                    } else if (r <= 1) {
                        statusText = 'Very Light Rain';
                        dotClass = 'bg-green-400';
                        shadowColor = 'rgba(74,222,128,0.5)';
                    } else if (r <= 5) {
                        statusText = 'Light Rain';
                        dotClass = 'bg-green-400';
                        shadowColor = 'rgba(74,222,128,0.5)';
                    } else if (r <= 10) {
                        statusText = 'Moderate Rain';
                        dotClass = 'bg-amber-400';
                        shadowColor = 'rgba(251,191,36,0.5)';
                    } else if (r <= 20) {
                        statusText = 'Heavy Rain';
                        dotClass = 'bg-red-400';
                        shadowColor = 'rgba(248,113,113,0.5)';
                    } else {
                        statusText = 'Very Heavy Rain';
                        dotClass = 'bg-red-400';
                        shadowColor = 'rgba(248,113,113,0.5)';
                    }
                    
                    // Update simple text values
                    const fields = {
                        'rainfall-value': parseFloat(data.rainfall_hourly).toFixed(2),
                        'rainfall-minute-value': parseFloat(data.rainfall).toFixed(2),
                        'status-value': statusText,
                        'temperature-value': data.temperature,
                        'humidity-value': data.humidity,
                        'water-level-value': data.water_level,
                        'lux-value': Math.round(data.lux),
                        'voltage-panel-value': data.voltage_panel,
                        'current-panel-value': data.current_panel,
                        'voltage-baterai-value': data.voltage_baterai,
                        'current-baterai-value': data.current_baterai,
                        'timertc-value': data.timertc,
                        'jitter-value': Math.round(data.jitter || 0).toLocaleString('id-ID'),
                        'delay-value': Math.round(data.delay || 0).toLocaleString('id-ID'),
                        'last-updated-text': 'Just now'
                    };

                    for (const [id, value] of Object.entries(fields)) {
                        const el = document.getElementById(id);
                        if (el) {
                            // Subtle animation on update
                            el.classList.add('animate__animated', 'animate__flash');
                            setTimeout(() => el.classList.remove('animate__animated', 'animate__flash'), 1000);
                            el.innerText = value;
                        }
                    }

                    // Update Water Level Status Badges
                    const wl = parseFloat(data.water_level);
                    const badgeAman = document.getElementById('wl-badge-aman');
                    const badgeWaspada = document.getElementById('wl-badge-waspada');
                    const badgeSiaga = document.getElementById('wl-badge-siaga');
                    const badgeBanjir = document.getElementById('wl-badge-banjir');

                    if (badgeAman && badgeWaspada && badgeSiaga && badgeBanjir) {
                        const isAman = (wl > 0.82);
                        const isWaspada = (wl > 0.62 && wl <= 0.82);
                        const isSiaga = (wl >= 0.40 && wl <= 0.62);
                        const isBanjir = (wl < 0.40);

                        // Aman
                        if (isAman) {
                            badgeAman.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-[#22C55E] text-white shadow-[0_0_12px_rgba(34,197,94,0.6)] scale-[1.03] border border-[#22C55E]";
                        } else {
                            badgeAman.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-transparent text-[#22C55E] border border-green-500/20 opacity-50";
                        }

                        // Waspada
                        if (isWaspada) {
                            badgeWaspada.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-[#F59E0B] text-white shadow-[0_0_12px_rgba(245,158,11,0.6)] scale-[1.03] border border-[#F59E0B]";
                        } else {
                            badgeWaspada.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-transparent text-[#F59E0B] border border-amber-500/20 opacity-50";
                        }

                        // Siaga
                        if (isSiaga) {
                            badgeSiaga.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-[#F97316] text-white shadow-[0_0_12px_rgba(249,115,22,0.6)] scale-[1.03] border border-[#F97316]";
                        } else {
                            badgeSiaga.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-transparent text-[#F97316] border border-orange-500/20 opacity-50";
                        }

                        // Banjir
                        if (isBanjir) {
                            badgeBanjir.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-[#EF4444] text-white shadow-[0_0_12px_rgba(239,68,68,0.6)] scale-[1.03] border border-[#EF4444]";
                        } else {
                            badgeBanjir.className = "text-center transition-all duration-300 rounded-lg py-1 px-0.5 text-[8px] min-[370px]:text-[9px] font-extrabold uppercase tracking-tighter xl:tracking-normal bg-transparent text-[#EF4444] border border-red-500/20 opacity-50";
                        }
                    }

                    // Update Status Dot Color
                    const statusDot = document.getElementById('status-dot');
                    if (statusDot) {
                        statusDot.className = `w-3 h-3 rounded-full ${dotClass}`;
                        statusDot.style.boxShadow = `0 0 12px ${shadowColor}`;
                    }

                    // Update Dynamic Background Video & Image
                    const video = document.getElementById('rainfall-video');
                    const bgImage = document.getElementById('rainfall-bg-image');
                    if (video) {
                        const videoBaseUrl = video.getAttribute('data-base-url');
                        const imageBaseUrl = bgImage ? bgImage.getAttribute('data-base-url') : '';
                        const videoMap = {
                            'No Rain': 'norain.mp4',
                            'Very Light Rain': 'verylightrain.mp4',
                            'Light Rain': 'lightrain.mp4',
                            'Moderate Rain': 'moderaterain.mp4',
                            'Heavy Rain': 'heavyrain.mp4',
                            'Very Heavy Rain': 'veryheavyrain.mp4'
                        };
                        const imageMap = {
                            'No Rain': 'norain.png',
                            'Very Light Rain': 'verylightrain.png',
                            'Light Rain': 'lightrain.png',
                            'Moderate Rain': 'moderaterain.png',
                            'Heavy Rain': 'heavyrain.png',
                            'Very Heavy Rain': 'veryheavyrain.png'
                        };
                        const videoFile = videoMap[statusText] || 'norain.mp4';
                        const imageFile = imageMap[statusText] || 'norain.png';
                        const targetSrc = videoBaseUrl + videoFile;
                        
                        if (!video.src.endsWith(videoFile)) {
                            // Hide video, update image first
                            video.classList.remove('opacity-100');
                            video.classList.add('opacity-0');
                            if (bgImage) bgImage.src = imageBaseUrl + imageFile;

                            video.src = targetSrc;
                            video.load();
                            video.play().catch(e => console.log('Video autoplay error:', e));
                        }
                    }

                    // Update Pump UI
                    const pumpCard = document.getElementById('pump-status-card');
                    const pumpIcon = document.getElementById('pump-status-icon');
                    const pumpText = document.getElementById('pump-status-text');
                    const isActive = data.status_pompa === true || data.status_pompa === 1 || data.status_pompa === '1' || data.status_pompa === 'true';
                    
                    // ── Rainfall threshold notification ──
                    const currRainfallHourly = parseFloat(data.rainfall_hourly) || 0;
                    if (lastRainfallHourly <= 10 && currRainfallHourly > 10) {
                        const rainfallTitle = '🌧️ Curah Hujan Tinggi';
                        const rainfallBody = `Curah hujan mencapai ${currRainfallHourly.toFixed(2)} mm/hour (melebihi batas 10 mm/hour)`;
                        triggerBrowserNotification(rainfallTitle, rainfallBody);
                        showToastNotification(rainfallTitle, rainfallBody, 'rainfall');
                    }
                    lastRainfallHourly = currRainfallHourly;

                    if (isActive && !lastPump1Status) {
                        const title = '🚨 Pump Activated';
                        const body = 'The water pump (Pump 1) has been switched ON';
                        triggerBrowserNotification(title, body);
                        showToastNotification(title, body, 'pump');
                    }
                    lastPump1Status = isActive;
                    
                    if (pumpCard && pumpIcon && pumpText) {
                        // Card classes
                        pumpCard.classList.toggle('bg-emerald-500', isActive);
                        pumpCard.classList.toggle('bg-slate-500', !isActive);
                        pumpCard.classList.toggle('shadow-emerald-500/20', isActive);
                        pumpCard.classList.toggle('shadow-slate-500/20', !isActive);
                        
                        // Icon animation
                        if (isActive) {
                            pumpIcon.classList.add('animate-spin');
                        } else {
                            pumpIcon.classList.remove('animate-spin');
                        }
                        
                        // Text and color
                        pumpText.innerText = isActive ? 'Active' : 'Off';
                        pumpText.classList.toggle('text-emerald-400', isActive);
                        pumpText.classList.toggle('text-slate-400', !isActive);
                    }

                    // Update Pump 2 UI
                    const pumpCard2 = document.getElementById('pump-status-card-2');
                    const pumpIcon2 = document.getElementById('pump-status-icon-2');
                    const pumpText2 = document.getElementById('pump-status-text-2');
                    const isActive2 = data.status_pompa2 === true || data.status_pompa2 === 1 || data.status_pompa2 === '1' || data.status_pompa2 === 'true';
                    
                    if (isActive2 && !lastPump2Status) {
                        const title = '🚨 Pump Activated';
                        const body = 'The water pump (Pump 2) has been switched ON';
                        triggerBrowserNotification(title, body);
                        showToastNotification(title, body, 'pump');
                    }
                    lastPump2Status = isActive2;
                    
                    if (pumpCard2 && pumpIcon2 && pumpText2) {
                        pumpCard2.classList.toggle('bg-emerald-500', isActive2);
                        pumpCard2.classList.toggle('bg-slate-500', !isActive2);
                        pumpCard2.classList.toggle('shadow-emerald-500/20', isActive2);
                        pumpCard2.classList.toggle('shadow-slate-500/20', !isActive2);
                        
                        if (isActive2) {
                            pumpIcon2.classList.add('animate-spin');
                        } else {
                            pumpIcon2.classList.remove('animate-spin');
                        }
                        
                        pumpText2.innerText = isActive2 ? 'Active' : 'Off';
                        pumpText2.classList.toggle('text-emerald-400', isActive2);
                        pumpText2.classList.toggle('text-slate-400', !isActive2);
                    }
                });
        }

        // Date validation for exporting data
        const exportStartDateInput = document.getElementById('export-start-date');
        const exportEndDateInput = document.getElementById('export-end-date');
        if (exportStartDateInput && exportEndDateInput) {
            exportStartDateInput.addEventListener('change', () => {
                if (exportStartDateInput.value) {
                    exportEndDateInput.min = exportStartDateInput.value;
                }
            });
            exportEndDateInput.addEventListener('change', () => {
                if (exportEndDateInput.value) {
                    exportStartDateInput.max = exportEndDateInput.value;
                }
            });
        }
    });
</script>

<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 3s linear infinite;
    }

</style>
@endsection
