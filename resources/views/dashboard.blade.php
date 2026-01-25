<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Tracker Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8" x-data="{ period: '{{ $period }}' }">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Visitor Statistics</h1>
            <p class="text-gray-600 mt-1">Monitor your website traffic and visitor analytics</p>
        </div>

        <!-- Period Filter -->
        <div class="mb-6">
            <form method="GET" class="flex gap-2">
                @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $value => $label)
                    <button type="submit" name="period" value="{{ $value }}"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                            {{ $period === $value ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Visitors -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Visitors</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($summary['total_visitors']) }}</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    <span class="text-green-600 font-medium">{{ number_format($summary['today_visitors']) }}</span> today
                </p>
            </div>

            <!-- Total Page Views -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Page Views</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($summary['total_page_views']) }}</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    <span class="text-green-600 font-medium">{{ number_format($summary['today_page_views']) }}</span> today
                </p>
            </div>

            <!-- Online Now -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Online Now</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">{{ number_format($summary['online_visitors']) }}</p>
                    </div>
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Active in last {{ config('visitor-tracker.online_threshold', 5) }} min</p>
            </div>

            <!-- Bounce Rate -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Bounce Rate</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">{{ $bounceRate }}%</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    <span class="font-medium">{{ $avgPagesPerVisit }}</span> pages/visit
                </p>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Top Pages -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Top Pages</h2>
                <div class="space-y-3">
                    @forelse($topPages as $page)
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate" title="{{ $page->path }}">
                                    /{{ $page->path }}
                                </p>
                            </div>
                            <div class="flex items-center gap-4 ml-4">
                                <span class="text-sm text-gray-500">{{ number_format($page->visits) }} views</span>
                                <span class="text-xs text-gray-400">{{ number_format($page->unique_visitors) }} unique</span>
                            </div>
                        </div>
                        @if(!$loop->last)
                            <div class="border-b border-gray-100"></div>
                        @endif
                    @empty
                        <p class="text-sm text-gray-500">No data available</p>
                    @endforelse
                </div>
            </div>

            <!-- Top Referrers -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Top Referrers</h2>
                <div class="space-y-3">
                    @forelse($topReferrers as $referrer)
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate" title="{{ $referrer->referrer }}">
                                    {{ Str::limit($referrer->referrer, 40) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-4 ml-4">
                                <span class="text-sm text-gray-500">{{ number_format($referrer->visits) }} visits</span>
                            </div>
                        </div>
                        @if(!$loop->last)
                            <div class="border-b border-gray-100"></div>
                        @endif
                    @empty
                        <p class="text-sm text-gray-500">No referrer data available</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Browser, Platform, Device Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Browsers -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Browsers</h2>
                <div class="space-y-3">
                    @php $totalBrowsers = $browsers->sum('count'); @endphp
                    @forelse($browsers as $browser)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700">{{ $browser->browser ?? 'Unknown' }}</span>
                                <span class="text-gray-500">{{ number_format($browser->count) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $totalBrowsers > 0 ? ($browser->count / $totalBrowsers * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No data available</p>
                    @endforelse
                </div>
            </div>

            <!-- Platforms -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Platforms</h2>
                <div class="space-y-3">
                    @php $totalPlatforms = $platforms->sum('count'); @endphp
                    @forelse($platforms as $platform)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700">{{ $platform->platform ?? 'Unknown' }}</span>
                                <span class="text-gray-500">{{ number_format($platform->count) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ $totalPlatforms > 0 ? ($platform->count / $totalPlatforms * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No data available</p>
                    @endforelse
                </div>
            </div>

            <!-- Devices -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Devices</h2>
                <div class="space-y-3">
                    @php
                        $totalDevices = $devices->sum('count');
                        $deviceColors = ['desktop' => 'bg-purple-600', 'mobile' => 'bg-yellow-500', 'tablet' => 'bg-pink-500'];
                    @endphp
                    @forelse($devices as $device)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-700">{{ ucfirst($device->device_type ?? 'Unknown') }}</span>
                                <span class="text-gray-500">{{ number_format($device->count) }} ({{ $totalDevices > 0 ? round($device->count / $totalDevices * 100, 1) : 0 }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="{{ $deviceColors[$device->device_type] ?? 'bg-gray-500' }} h-2 rounded-full" style="width: {{ $totalDevices > 0 ? ($device->count / $totalDevices * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No data available</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Countries -->
        @if($countries->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Top Countries</h2>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @foreach($countries as $country)
                    <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
                        <span class="text-lg">{{ $country->country_code ? country_flag($country->country_code) : '' }}</span>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $country->country ?? $country->country_code }}</p>
                            <p class="text-xs text-gray-500">{{ number_format($country->count) }} visitors</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="text-center text-sm text-gray-500">
            <p>Powered by <a href="https://github.com/ghdj/laravel-visitor-tracker" class="text-blue-600 hover:underline">Laravel Visitor Tracker</a></p>
        </div>
    </div>

</body>
</html>
