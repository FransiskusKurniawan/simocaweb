@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6 animate__animated animate__fadeIn">
    
    <!-- Header Section -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-slate-50 shadow-sm transition-all">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-800">
                    Notifications
                </h2>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                    System Alerts & Logs
                </p>
            </div>
        </div>

        @if($notifications->where('is_read', false)->count() > 0)
            <form action="{{ route('notification.read-all') }}" method="POST">
                @csrf
                <button type="submit" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-100 px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 active:scale-95 shadow-sm">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    Mark all as read
                </button>
            </form>
        @endif
    </div>

    <!-- Notifications List -->
    @if($notifications->isEmpty())
        <!-- Empty State -->
        <div class="bg-white rounded-[2.5rem] p-12 text-center border border-slate-100 shadow-sm">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                <i data-lucide="bell-off" class="w-10 h-10"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-700">No Notifications</h3>
            <p class="text-slate-400 text-sm mt-1 max-w-xs mx-auto">You're all caught up! System alerts and logs will appear here.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($notifications as $notification)
                <div class="relative bg-white border border-slate-100 {{ !$notification->is_read ? 'shadow-lg shadow-indigo-500/5 ring-1 ring-indigo-500/10' : 'shadow-sm' }} rounded-[2rem] p-5 flex gap-4 transition-all hover:scale-[1.01]">
                    
                    <!-- Unread Badge Indicator -->
                    @if(!$notification->is_read)
                        <span class="absolute top-6 right-6 w-2.5 h-2.5 bg-indigo-500 rounded-full animate-pulse"></span>
                    @endif

                    <!-- Icon Container -->
                    <div class="w-12 h-12 {{ !$notification->is_read ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-50 text-slate-400' }} rounded-2xl flex items-center justify-center flex-shrink-0 shadow-inner">
                        @if($notification->type === 'pump')
                            <i data-lucide="cog" class="w-6 h-6 {{ !$notification->is_read ? 'animate-spin' : '' }}" style="animation-duration: 4s;"></i>
                        @else
                            <i data-lucide="bell" class="w-6 h-6"></i>
                        @endif
                    </div>

                    <!-- Message Body -->
                    <div class="flex-1 min-w-0 pr-6">
                        <h4 class="text-base font-black text-slate-800 tracking-tight leading-snug mb-1">
                            {{ $notification->title }}
                        </h4>
                        <p class="text-xs text-slate-500 font-medium leading-relaxed mb-2.5">
                            {{ $notification->body }}
                        </p>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                    </div>

                </div>
            @endforeach
        </div>

        <!-- Pagination Links -->
        <div class="pt-4">
            {{ $notifications->links() }}
        </div>
    @endif

</div>
@endsection
