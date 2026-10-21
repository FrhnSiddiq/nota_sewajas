<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @php
        $appName = \App\Services\SettingsService::get('app_name', 'SewaJas');
        $appTagline = \App\Services\SettingsService::get('app_tagline', 'RENTAL JAS');
        $appLogo = \App\Services\SettingsService::get('app_logo');
        $appLogoUrl = '';
        $faviconUrl = $appLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($appLogo)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo)
            : asset('favicon.ico');
        if ($appLogo && \Illuminate\Support\Facades\Storage::disk('public')->exists($appLogo)) {
            $fullPath = storage_path('app/public/' . $appLogo);
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            $appLogoUrl = 'data:image/' . $extension . ';base64,' . base64_encode(file_get_contents($fullPath));
        }
    @endphp
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — {{ $appName }}</title>
    <meta name="description" content="Login {{ $appName }} - {{ $appTagline }}" />
    <meta property="og:title" content="{{ $appName }}" />
    <meta property="og:description" content="{{ $appTagline }}" />
    <link rel="icon" href="{{ $faviconUrl }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">

    {{-- Tailwind CDN (login page standalone) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"DM Sans"', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: { DEFAULT: '#2563eb', 700: '#1d4ed8' },
                        surface: { DEFAULT: '#f8fafc' },
                        ring: { DEFAULT: 'rgba(37, 99, 235, .35)' },
                    },
                    boxShadow: {
                        card: '0 8px 30px rgba(15, 23, 42, .08)',
                    },
                    transitionTimingFunction: {
                        fast: 'cubic-bezier(.2,.8,.2,1)'
                    },
                }
            }
        }
    </script>

    <style>
        .login-page body {
            min-height: 100vh;
            background: #f4f8ff;
            color: #0f172a;
            font-family: "DM Sans", system-ui, sans-serif;
            margin: 0;
        }
        .login-page .login-hero {
            display: none;
        }
        .login-page .login-main {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100svh;
            padding: 0;
        }
        .login-page .login-panel {
            display: flex;
            align-items: stretch;
            width: 100%;
            min-height: 100svh;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 18px 60px rgba(37, 99, 235, .08);
        }
        .login-page .login-card {
            width: 100%;
            max-width: 29rem;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 14px 40px rgba(15, 23, 42, .08);
            border: 1px solid #edf2f8;
            overflow: hidden;
        }
        .login-page .login-card-header {
            padding: 2.25rem 2.5rem 1rem;
            border-bottom: 0;
        }
        .login-page .login-card-body {
            padding: 1rem 2.5rem 1.25rem;
        }
        .login-page .login-card-footer {
            padding: 1.25rem 2.5rem 2.25rem;
        }
        .login-page .login-input {
            width: 100%;
            box-sizing: border-box;
            border-radius: 0.7rem;
            border: 1px solid #d8e1ef;
            background: #fbfdff;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            outline: none;
        }
        .login-page .login-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .15);
        }
        .login-page .login-btn {
            width: 100%;
            border-radius: 0.7rem;
            background: linear-gradient(135deg, #2f7cf6, #2168ee);
            color: #ffffff;
            font-weight: 700;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            border: 1px solid rgba(37, 99, 235, .2);
            cursor: pointer;
        }
        .login-page .login-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }
        .login-page .login-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #172b57;
            margin-bottom: 0.5rem;
        }
        .login-page .login-eyebrow {
            color: #2874ed;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .login-page .login-heading {
            color: #102557;
            font-size: clamp(1.65rem, 2.2vw, 2rem);
            line-height: 1.15;
        }
        @media (min-width: 1024px) {
            .login-page .login-hero {
                display: flex;
                width: 50%;
                min-height: 100svh;
                position: relative;
                overflow: hidden;
                background: linear-gradient(145deg, #255fd5 0%, #1749b9 62%, #10399d 100%);
                color: #ffffff;
            }
            .login-page .login-main {
                padding: 0;
            }
            .login-page .login-hero::before,
            .login-page .login-hero::after {
                content: "";
                position: absolute;
                border: 1px solid rgba(255, 255, 255, .09);
                pointer-events: none;
            }
            .login-page .login-hero::before {
                width: 23rem;
                height: 23rem;
                left: -9rem;
                top: -10rem;
                border-width: 0 1px 1px 0;
                border-radius: 50%;
                box-shadow: 0 0 0 3.5rem rgba(255, 255, 255, .035), 0 0 0 7rem rgba(255, 255, 255, .025);
            }
            .login-page .login-hero::after {
                width: 14rem;
                height: 14rem;
                right: -5rem;
                bottom: -5rem;
                border-radius: 2rem;
                transform: rotate(12deg);
                box-shadow: -5rem -2rem 0 -1px rgba(255, 255, 255, .05), -10rem -4rem 0 -1px rgba(255, 255, 255, .04);
            }
            .login-page .login-form-column {
                width: 50%;
                background: #f7faff;
            }
        }
        @media (max-width: 639px) {
            .login-page .login-card {
                border: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .login-page .login-card-header,
            .login-page .login-card-body,
            .login-page .login-card-footer {
                padding-left: 1.25rem;
                padding-right: 1.25rem;
            }
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="login-page min-h-screen bg-slate-50 text-slate-900 font-sans overflow-x-hidden" x-data="loginPage()" :class="{ 'opacity-60 pointer-events-none': loading }">
    {{-- Mobile top bar --}}
    <div class="lg:hidden fixed top-0 inset-x-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur">
        <div class="flex items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    @if($appLogoUrl)
                        <img src="{{ $appLogoUrl }}" alt="{{ $appName }} Logo" class="h-10 w-10 rounded-xl object-contain">
                    @else
                        <div class="h-10 w-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 7h18l-1 14H4L3 7z"/>
                                <path d="M7 7l1-4h8l1 4"/>
                                <path d="M12 11v10"/>
                                <path d="M8 21h8"/>
                            </svg>
                        </div>
                    @endif
                    <div>
                    <div class="font-extrabold leading-none">{{ $appName }}</div>
                    <div class="text-[10px] font-semibold uppercase tracking-widest text-slate-500 leading-none mt-1">{{ $appTagline }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="min-h-screen flex items-center justify-center login-main">
        <div class="login-panel">
            {{-- Desktop hero + form --}}
            <div class="hidden lg:flex w-5/12 relative overflow-hidden login-hero">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, rgba(255,255,255,.8) 1px, transparent 1px); background-size: 18px 18px;"></div>
                <div class="absolute inset-0 opacity-25" style="background-image: linear-gradient(135deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 60%);"></div>

                <div class="relative z-10 flex flex-col justify-center w-full px-10 py-12 text-white">
                <div class="max-w-md">
                    <div class="flex items-center gap-3">
                        @if($appLogoUrl)
                            <img src="{{ $appLogoUrl }}" alt="{{ $appName }} Logo" class="h-12 w-12 rounded-2xl object-contain">
                        @else
                            <div class="h-12 w-12 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 7h18l-1 14H4L3 7z"/>
                                    <path d="M7 7l1-4h8l1 4"/>
                                    <path d="M12 11v10"/>
                                    <path d="M8 21h8"/>
                                </svg>
                            </div>
                        @endif
                        <div>
                            <div class="text-3xl font-extrabold leading-tight">
                                {{ $appName }}
                            </div>
                            <div class="text-sm font-semibold text-white/80">{{ $appTagline }}</div>
                        </div>
                    </div>

                    <h2 class="mt-6 text-3xl font-extrabold tracking-tight">Kelola penyewaan dengan cepat & rapi.</h2>
                    <p class="mt-3 text-white/85 leading-relaxed">Login untuk mengakses dashboard: customer, rental, invoice, receipt, broadcast, dan report.</p>

                    <div class="mt-8 grid grid-cols-1 gap-3">
                        <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
                            <div class="flex items-center gap-3">
                                <span class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2v20"/>
                                        <path d="M17 5H9"/>
                                        <path d="M7 5h10"/>
                                        <path d="M5 12h14"/>
                                    </svg>
                                </span>
                                <div>
                                    <div class="font-bold">Cepat</div>
                                    <div class="text-sm text-white/80">Proses rental, payment, return</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
                            <div class="flex items-center gap-3">
                                <span class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                </span>
                                <div>
                                    <div class="font-bold">Aman</div>
                                    <div class="text-sm text-white/80">Scope cabang & role-based access</div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl bg-white/10 border border-white/15 p-4">
                            <div class="flex items-center gap-3">
                                <span class="h-10 w-10 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16v16H4z"/>
                                        <path d="M8 8h8"/>
                                        <path d="M8 12h6"/>
                                    </svg>
                                </span>
                                <div>
                                    <div class="font-bold">Modern</div>
                                    <div class="text-sm text-white/80">Invoice, receipt & dashboard insight</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center gap-3 text-white/80">
                        <div class="h-2 w-2 rounded-full bg-white"></div>
                        <div class="text-sm">Tampilan dirancang responsif untuk desktop & mobile.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main form column --}}
        <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-10 py-16 lg:py-10 login-form-column">
            <div class="w-full max-w-md">
                {{-- Login card --}}
                <div class="bg-white rounded-3xl shadow-card border border-slate-200 overflow-hidden login-card">
                    <div class="login-card-header">
                        <div class="login-eyebrow">{{ $appName }} Login</div>
                        <h1 class="login-heading mt-4 font-extrabold tracking-tight">Selamat Datang Kembali</h1>
                        <p class="text-sm text-slate-500 mt-2">Masuk untuk mengelola rental Anda</p>
                    </div>

                    <div class="px-6 sm:px-7 py-6">
                        {{-- Alerts --}}
                        @if ($errors->any())
                            <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                                <div class="font-semibold flex items-center gap-2">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <path d="M12 9v4"/>
                                        <path d="M12 17h.01"/>
                                    </svg>
                                    Terjadi kesalahan
                                </div>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($errors->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (session('success'))
                            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                                {{ session('success') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.post') }}" @submit="loading = true" class="space-y-4" x-ref="loginForm">
                            @csrf

                            {{-- Email --}}
                            <div>
                                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Email</label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M3 8l4.5 3.5L12 7l4.5 4.5L21 8"/>
                                            <path d="M21 8v10H3V8"/>
                                        </svg>
                                    </div>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        placeholder="email@domain.com"
                                        autocomplete="email"
                                        required autofocus
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-4 py-3 text-sm outline-none transition duration-150 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/15"
                                    >
                                </div>
                                @error('email')
                                    <div class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Password --}}
                            <div>
                                <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Password</label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M12 15v2m0 4h.01"/>
                                            <path d="M16 8a4 4 0 0 0-8 0v3h10V8z"/>
                                            <path d="M6 11v10h12V11"/>
                                        </svg>
                                    </div>

                                    <input
                                        :type="showPass ? 'text' : 'password'"
                                        id="password"
                                        name="password"
                                        placeholder="••••••••"
                                        autocomplete="current-password"
                                        required
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-12 py-3 text-sm outline-none transition duration-150 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/15"
                                    >

                                    <button
                                        type="button"
                                        @click="showPass = !showPass"
                                        class="absolute inset-y-0 right-3 flex items-center text-slate-500 hover:text-blue-700 transition duration-150"
                                        aria-label="Toggle password"
                                    >
                                        <svg x-show="!showPass" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 12s3 7 10 7 10-7 10-7-3-7-10-7S2 12 2 12z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <svg x-show="showPass" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 1l22 22"/>
                                            <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"/>
                                            <path d="M9.88 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a17.16 17.16 0 0 1-4.1 5.53"/>
                                            <path d="M6.11 6.11C3.06 8.18 2 12 2 12s3 7 10 7a10.4 10.4 0 0 0 4.3-.93"/>
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <div class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Remember me --}}
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm text-slate-600 select-none">
                                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600/20" {{ old('remember') ? 'checked' : '' }}>
                                    Ingat saya
                                </label>
                            </div>

                            {{-- Submit --}}
                            <button
                                type="submit"
                                :disabled="loading"
                                class="w-full rounded-2xl bg-blue-600 text-white font-bold py-3.5 px-4 text-sm shadow-sm border border-blue-700/20 transition duration-150 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/25 active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                <span class="inline-flex items-center justify-center gap-2">
                                    <svg x-show="loading" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                    </svg>
                                    <span x-show="!loading" class="flex items-center gap-2">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M5 12h14"/>
                                            <path d="M13 5l7 7-7 7"/>
                                        </svg>
                                        Masuk
                                    </span>
                                    <span x-show="loading" class="font-semibold">Memproses...</span>
                                </span>
                            </button>
                        </form>
                    </div>

                    {{-- Footer --}}
                    <div class="px-6 sm:px-7 pb-7">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="text-xs text-slate-500 font-semibold">© {{ date('Y') }} {{ $appName }}</div>
                            <div class="text-xs text-slate-400">{{ $appTagline }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

<script>
        function loginPage() {
            return { showPass: false, loading: false }
        }
    </script>
</body>
</html>
