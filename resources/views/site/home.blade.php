<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attributa - Rastreamento Inteligente de Campanhas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        attributa: {
                            dark: '#0F172A',
                            primary: '#1E2A78',
                            accent: '#3B82F6',
                            light: '#60A5FA',
                            surface: '#1E293B'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.8s ease-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #1E2A78 0%, #0F172A 50%, #0F172A 100%);
            min-height: 100vh;
        }
        .glass-effect {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        .dashboard-glow {
            box-shadow: 0 0 60px rgba(59, 130, 246, 0.15);
        }
        .chart-bar {
            transition: height 1s ease-out;
        }
    </style>
</head>
<body class="text-gray-100 font-sans antialiased overflow-x-hidden">

    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-50 glass-effect border-b border-blue-500/10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-white tracking-tight">Attributa</span>
                </div>
                <!-- Login Button -->
                <a href="{{ route('panel.index') }}" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition-all duration-300 shadow-lg shadow-blue-600/25 hover:shadow-blue-600/40">
                    Login
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="relative pt-32 pb-20 lg:pt-40 lg:pb-32 overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl animate-pulse-slow"></div>
            <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-blue-500/5 rounded-full blur-3xl animate-pulse-slow" style="animation-delay: 1.5s;"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 text-center">
            <!-- Headline -->
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 animate-slide-up leading-tight">
                Controle total sobre<br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-blue-600">suas campanhas.</span>
            </h1>

            <!-- Subtitle -->
            <p class="mt-6 text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto animate-slide-up" style="animation-delay: 0.1s;">
                Rastreamento inteligente de conversões, pixels e performance em um único painel.
            </p>

            <!-- CTA Buttons -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up" style="animation-delay: 0.2s;">
                <button class="w-full sm:w-auto px-8 py-4 text-base font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition-all duration-300 shadow-xl shadow-blue-600/30 hover:shadow-blue-600/50 hover:-translate-y-0.5">
                    Começar agora
                </button>
                <a href="{{ route('panel.index') }}" class="w-full sm:w-auto px-8 py-4 text-base font-medium text-gray-400 hover:text-white border border-gray-700 hover:border-gray-600 rounded-xl transition-all duration-300 hover:bg-white/5">
                    Acessar painel
                </a>
            </div>

            <!-- Dashboard Mockup -->
            <div class="mt-16 lg:mt-24 relative animate-fade-in" style="animation-delay: 0.4s;">
                <div class="relative mx-auto max-w-5xl">
                    <!-- Glow Effect -->
                    <div class="absolute -inset-1 bg-gradient-to-r from-blue-600/20 to-blue-400/20 rounded-2xl blur-xl"></div>
                    
                    <!-- Dashboard Container -->
                    <div class="relative bg-slate-900/90 backdrop-blur-xl rounded-2xl border border-slate-700/50 dashboard-glow overflow-hidden">
                        <!-- Browser Chrome -->
                        <div class="flex items-center px-4 py-3 bg-slate-800/50 border-b border-slate-700/50">
                            <div class="flex space-x-2">
                                <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                                <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                            </div>
                            <div class="flex-1 flex justify-center">
                                <div class="px-4 py-1.5 bg-slate-900/80 rounded-md text-xs text-gray-500 font-mono">
                                    app.attributa.io/dashboard
                                </div>
                            </div>
                        </div>

                        <!-- Dashboard Content -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Metric Cards -->
                            <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs text-gray-400 uppercase tracking-wider">Conversões</span>
                                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                        </svg>
                                    </div>
                                    <div class="text-2xl font-bold text-white">2,847</div>
                                    <div class="text-xs text-green-400 mt-1">+12.5% vs ontem</div>
                                </div>
                                <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs text-gray-400 uppercase tracking-wider">Taxa de Conv.</span>
                                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                    <div class="text-2xl font-bold text-white">4.2%</div>
                                    <div class="text-xs text-blue-400 mt-1">+0.8% vs ontem</div>
                                </div>
                                <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs text-gray-400 uppercase tracking-wider">Receita</span>
                                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="text-2xl font-bold text-white">R$ 45.2k</div>
                                    <div class="text-xs text-purple-400 mt-1">+23.1% vs ontem</div>
                                </div>
                            </div>

                            <!-- Chart Area -->
                            <div class="lg:col-span-2 bg-slate-800/30 rounded-xl p-6 border border-slate-700/50">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-sm font-semibold text-gray-300">Performance de Campanhas</h3>
                                    <div class="flex space-x-2">
                                        <span class="px-2 py-1 text-xs bg-blue-600/20 text-blue-400 rounded">7 dias</span>
                                    </div>
                                </div>
                                <div class="h-48 flex items-end justify-between space-x-2">
                                    <div class="flex-1 bg-blue-600/40 rounded-t chart-bar" style="height: 40%"></div>
                                    <div class="flex-1 bg-blue-600/50 rounded-t chart-bar" style="height: 65%"></div>
                                    <div class="flex-1 bg-blue-600/60 rounded-t chart-bar" style="height: 45%"></div>
                                    <div class="flex-1 bg-blue-600/70 rounded-t chart-bar" style="height: 80%"></div>
                                    <div class="flex-1 bg-blue-500/80 rounded-t chart-bar" style="height: 55%"></div>
                                    <div class="flex-1 bg-blue-500/90 rounded-t chart-bar" style="height: 90%"></div>
                                    <div class="flex-1 bg-blue-500 rounded-t chart-bar" style="height: 75%"></div>
                                </div>
                                <div class="flex justify-between mt-2 text-xs text-gray-500">
                                    <span>Seg</span>
                                    <span>Ter</span>
                                    <span>Qua</span>
                                    <span>Qui</span>
                                    <span>Sex</span>
                                    <span>Sab</span>
                                    <span>Dom</span>
                                </div>
                            </div>

                            <!-- Side Panel -->
                            <div class="bg-slate-800/30 rounded-xl p-6 border border-slate-700/50">
                                <h3 class="text-sm font-semibold text-gray-300 mb-4">Fontes de Tráfego</h3>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-blue-600/20 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                                </svg>
                                            </div>
                                            <span class="text-sm text-gray-300">Facebook</span>
                                        </div>
                                        <span class="text-sm font-medium text-white">45%</span>
                                    </div>
                                    <div class="w-full bg-slate-700/50 rounded-full h-1.5">
                                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: 45%"></div>
                                    </div>

                                    <div class="flex items-center justify-between pt-2">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-red-600/20 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                                </svg>
                                            </div>
                                            <span class="text-sm text-gray-300">YouTube</span>
                                        </div>
                                        <span class="text-sm font-medium text-white">32%</span>
                                    </div>
                                    <div class="w-full bg-slate-700/50 rounded-full h-1.5">
                                        <div class="bg-red-500 h-1.5 rounded-full" style="width: 32%"></div>
                                    </div>

                                    <div class="flex items-center justify-between pt-2">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-green-600/20 rounded-lg flex items-center justify-center">
                                                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                                </svg>
                                            </div>
                                            <span class="text-sm text-gray-300">Orgânico</span>
                                        </div>
                                        <span class="text-sm font-medium text-white">23%</span>
                                    </div>
                                    <div class="w-full bg-slate-700/50 rounded-full h-1.5">
                                        <div class="bg-green-500 h-1.5 rounded-full" style="width: 23%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/50 bg-slate-950/50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center space-x-2">
                    <div class="w-6 h-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-semibold text-gray-400">Attributa</span>
                </div>
                <p class="text-xs text-gray-500">
                    © 2026 Attributa. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Animate chart bars on load
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.chart-bar');
            bars.forEach((bar, index) => {
                const finalHeight = bar.style.height;
                bar.style.height = '0%';
                setTimeout(() => {
                    bar.style.height = finalHeight;
                }, 600 + (index * 100));
            });
        });
    </script>
</body>
</html>