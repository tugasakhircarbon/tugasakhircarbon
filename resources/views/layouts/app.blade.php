<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ config('app.name', 'Carbon Marketplace') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#10B981',
                        secondary: '#3B82F6',
                        dark: '#1F2937',
                        light: '#F9FAFB',
                    }
                }
            }
        }
    </script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
        }
        .sidebar {
            transition: all 0.3s ease;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                left: -100%;
                z-index: 50;
            }
            .sidebar.active {
                left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="sidebar bg-white w-64 border-r border-gray-200 flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-leaf text-primary text-2xl"></i>
                    <h1 class="text-xl font-bold text-dark">CarbonTrade</h1>
                </div>
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <a href="{{ url('/dashboard') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->is('dashboard') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                @auth
                    <a href="{{ route('transactions.index') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('transactions.*') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transaksi</span>
                    </a>
                    <a href="{{ route('carbon-credits.index') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ (request()->routeIs('carbon-credits.*') && !request()->routeIs('carbon-credits.vehicles')) ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-coins"></i>
                        <span>Kelola Kuota</span>
                    </a>
                    <a href="{{ route('carbon-credits.vehicles') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('carbon-credits.vehicles') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-car-side"></i>
                        <span>Kendaraan</span>
                    </a>

                    <a href="{{ route('emission.monitoring') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('emission.monitoring') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-chart-line mr-1"></i>
                        <span>Monitoring Emisi</span>
                    </a>

                    <a href="{{ route('devices.index') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('devices.index') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-microchip mr-1"></i>
                        <span>Device Sensor</span>
                    </a>

                    @if(Auth::user()->isAdmin())
                        <a href="{{ route('admin.marketplace') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('admin.marketplace') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            <i class="fas fa-store"></i>
                            <span>Marketplace Admin</span>
                        </a>
                    @else
                        <a href="{{ route('marketplace') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('marketplace') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            <i class="fas fa-store"></i>
                            <span>Marketplace</span>
                        </a>
                    @endif
                    <a href="{{ route('payouts.index') }}" class="flex items-center space-x-3 p-3 rounded-lg {{ request()->routeIs('payouts.*') ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Pencairan</span>
                    </a>
                @endauth
            </nav>
            @auth
            <div class="p-4 border-t border-gray-200">
                <div class="flex items-center space-x-3">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}" alt="User" class="w-10 h-10 rounded-full" />
                    <div>
                        <p class="font-medium text-dark">{{ Auth::user()->name }}</p>
                        <p class="text-sm text-gray-500">{{ Auth::user()->isAdmin() ? 'Admin' : 'User' }}</p>
                    </div>
                </div>
            </div>
            @endauth
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200 p-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button id="sidebarToggle" class="md:hidden text-gray-600" aria-label="Toggle sidebar">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    @php
                        $routeName = request()->route() ? request()->route()->getName() : '';
                        $pageTitles = [
                            'dashboard' => 'Dashboard',
                            'transactions.index' => 'Transaksi',
                            'carbon-credits.index' => 'Kuota Karbon',
                            'carbon-credits.vehicles' => 'Kelola Kendaraan',
                            'marketplace' => 'Marketplace',
                            'admin.marketplace' => 'Marketplace Admin',
                            'payouts.index' => 'Pencairan',
                        ];
                        $defaultTitle = 'Dashboard';
                        $title = $pageTitles[$routeName] ?? $defaultTitle;
                    @endphp
                    <h2 class="text-xl font-semibold text-dark">
                        @hasSection('header')
                            @yield('header')
                        @else
                            {{ $title }}
                        @endif
                    </h2>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                    </div>
                    <button class="p-2 text-gray-600 hover:bg-gray-100 rounded-full" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 border border-green-200 rounded text-green-700 flex items-center space-x-2" role="alert">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-200 rounded text-red-700 flex items-center space-x-2" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-100 border border-red-200 rounded text-red-700" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>

    @yield('scripts')
</body>
</html>
