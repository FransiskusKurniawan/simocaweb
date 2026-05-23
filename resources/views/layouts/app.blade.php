<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SIMOCA') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <!-- Alpine.js CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Pusher & Echo -->
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
    <script>
        window.Pusher = Pusher;
        const isHttps = window.location.protocol === 'https:';
        const wsHost = '{{ env("PUSHER_HOST", "127.0.0.1") }}';
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env("PUSHER_APP_KEY") }}',
            cluster: '{{ env("PUSHER_APP_CLUSTER", "mt1") }}',
            wsHost: wsHost === '127.0.0.1' ? window.location.hostname : wsHost,
            wsPort: isHttps ? 443 : {{ env("PUSHER_PORT", 6001) }},
            wssPort: isHttps ? 443 : {{ env("PUSHER_PORT", 6001) }},
            forceTLS: isHttps,
            disableStats: true,
            enabledTransports: ['ws', 'wss']
        });
    </script>

    <style>
        [x-cloak] { display: none !important; }
        
        body {
            background-color: #F8FAFC;
            color: #1E293B;
            -webkit-tap-highlight-color: transparent;
        }

        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(241, 245, 249, 0.8);
        }

        .bottom-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border-top: 1px solid rgba(241, 245, 249, 0.8);
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
</head>
<body class="font-sans antialiased">
    
    <!-- Desktop Header -->
    <header class="sticky top-0 z-50 glass-nav h-16 flex items-center px-4 md:px-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 flex items-center justify-center overflow-hidden">
                <img src="{{ asset('images/logosimocanobg.png') }}" class="w-10 h-10 object-contain" alt="SIMOCA Logo">
            </div>
            <div>
                <h1 class="text-lg font-extrabold tracking-tight text-slate-800">SIMOCA</h1>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-4">
            <!-- Notifications (Optional) -->
            <button class="w-10 h-10 rounded-full flex items-center justify-center text-slate-500 hover:bg-slate-100 transition-colors relative">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
            </button>
            
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-500 text-red-500 hover:text-white rounded-2xl transition-all shadow-sm font-bold text-sm group">
                    <i data-lucide="log-out" class="w-4 h-4 transition-transform group-hover:scale-110"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Sidebar (Optional Desktop) -->
    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6 md:p-8">
        @yield('content')
    </main>



    <script>
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>
