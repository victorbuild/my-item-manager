<template>
    <div class="min-h-screen p-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📊 統計分析</h1>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="mt-4 text-gray-600">載入統計資料中...</p>
        </div>

        <!-- Content -->
        <div v-else class="space-y-4">
            <!-- Period Selector -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">選擇時間範圍</h2>
                </div>
                
                <!-- 時間範圍選項 -->
                <div class="grid grid-cols-5 gap-2 mb-4">
                    <button
                        v-for="period in allPeriods"
                        :key="period.value"
                        @click="handlePeriodClick(period.value)"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                            selectedPeriod === period.value
                                ? 'bg-blue-600 text-white shadow-md'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        ]"
                    >
                        {{ period.label }}
                    </button>
                </div>

                <!-- 年份選擇器（僅在選擇年度時顯示） -->
                <div v-if="selectedPeriod === 'year'" class="mt-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">選擇年份</label>
                    <select
                        v-model="selectedYear"
                        @change="fetchStatistics"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                        <option v-for="year in availableYears" :key="year" :value="year">
                            {{ year }} 年
                        </option>
                    </select>
                </div>
            </div>

            <!-- 統計時間區間卡片 -->
            <div v-if="statistics.date_range" class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-600 mb-1">📅 統計時間區間</div>
                        <div class="text-lg font-semibold text-gray-800">
                            {{ formatDateRange(statistics.date_range) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- 進出平衡卡片 -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold text-gray-800">
                            ⚖️ 進出平衡
                        </h2>
                        <div class="relative group">
                            <button
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                title="說明"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                            
                            <!-- Tooltip -->
                            <div class="absolute left-1/2 -translate-x-1/2 top-6 z-50 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none">
                                <div class="space-y-1.5">
                                    <div class="font-semibold mb-2 text-white">進出平衡說明：</div>
                                    <div class="flex items-start gap-2">
                                        <span>•</span>
                                        <span>顯示這段期間新增的物品數量 vs 這段期間棄用的物品數量</span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span>•</span>
                                        <span>幫助您了解物品進出是否平衡，維持房間物品數量穩定</span>
                                    </div>
                                </div>
                                <!-- Tooltip 箭頭 -->
                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 transform rotate-45"></div>
                            </div>
                        </div>
                    </div>
                    <span class="text-xs text-gray-500">
                        {{ getPeriodLabel() }}
                    </span>
                </div>
                
                <div class="space-y-4">
                    <!-- 主要數值展示 -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- 新增物品 -->
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border-l-4 border-green-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">新增物品</span>
                                <span class="text-lg">📦</span>
                            </div>
                            <div class="text-3xl font-bold text-green-600 mb-1">
                                {{ statistics.totals?.created || 0 }}
                            </div>
                            <div class="text-xs text-gray-500">件</div>
                        </div>

                        <!-- 丟棄物品 -->
                        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-4 border-l-4 border-red-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">丟棄物品</span>
                                <span class="text-lg">🗑️</span>
                            </div>
                            <div class="text-3xl font-bold text-red-600 mb-1">
                                {{ statistics.totals?.discarded || 0 }}
                            </div>
                            <div class="text-xs text-gray-500">件</div>
                        </div>
                    </div>

                    <!-- 平衡指標 -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">平衡狀態</span>
                                <div class="relative group">
                                    <button
                                        class="text-gray-400 hover:text-gray-600 transition-colors"
                                        title="說明"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Tooltip -->
                                    <div class="absolute left-1/2 -translate-x-1/2 top-6 z-50 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none">
                                        <div class="space-y-1.5">
                                            <div class="font-semibold mb-2 text-white">平衡狀態說明：</div>
                                            <div class="flex items-start gap-2">
                                                <span>•</span>
                                                <span>空間不變保持平衡維持</span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <span>•</span>
                                                <span>如果覺得房間凌亂或許可以減少一點</span>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <span>•</span>
                                                <span>空間如果還有餘韻買一些新東西也不錯</span>
                                            </div>
                                        </div>
                                        <!-- Tooltip 箭頭 -->
                                        <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 transform rotate-45"></div>
                                    </div>
                                </div>
                            </div>
                            <span :class="[
                                'text-sm font-semibold px-2 py-1 rounded',
                                balanceStatusText === '完美平衡'
                                    ? 'bg-green-100 text-green-700' 
                                    : balanceStatusText === '接近平衡'
                                    ? 'bg-blue-100 text-blue-700'
                                    : balanceStatusText === '增加'
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-purple-100 text-purple-700'
                            ]">
                                {{ balanceStatusText }}
                            </span>
                        </div>
                        
                        <!-- 平衡進度條 -->
                        <div class="relative h-3 bg-gray-200 rounded-full overflow-hidden mb-3">
                            <div 
                                :class="[
                                    'h-full transition-all duration-500 rounded-full',
                                    balanceStatusText === '完美平衡'
                                        ? 'bg-green-500' 
                                        : balanceStatusText === '接近平衡'
                                        ? 'bg-blue-500'
                                        : balanceStatusText === '增加'
                                        ? 'bg-blue-500'
                                        : 'bg-purple-500'
                                ]"
                                :style="{ width: `${balancePercentage}%` }"
                            ></div>
                        </div>
                        
                        <!-- 淨增加數值 -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">淨增加</span>
                            <span :class="[
                                'text-xl font-bold',
                                netChange === 0 
                                    ? 'text-green-600' 
                                    : netChange > 0 
                                    ? 'text-blue-600' 
                                    : 'text-purple-600'
                            ]">
                                {{ netChange > 0 ? '+' : '' }}{{ netChange }} 件
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold text-gray-800">
                            物品狀態總覽
                            <span class="text-sm font-normal text-gray-500">
                                ({{ getPeriodLabel() }}新增的物品)
                            </span>
                        </h2>
                        <div class="relative group">
                            <button
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                                title="說明"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                            
                            <!-- Tooltip -->
                            <div class="absolute left-1/2 -translate-x-1/2 top-6 z-50 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none">
                                <div class="space-y-1.5">
                                    <div class="font-semibold mb-2 text-white">物品狀態總覽說明：</div>
                                    <div class="flex items-start gap-2">
                                        <span>•</span>
                                        <span>觀察這段期間新增的物品，目前各狀態的分布情況</span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span>•</span>
                                        <span>幫助您了解購買決策的品質（例如：有多少物品買來後一直未使用）</span>
                                    </div>
                                </div>
                                <!-- Tooltip 箭頭 -->
                                <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 transform rotate-45"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-4 text-sm text-gray-600">
                    {{ getPeriodLabel() }}新增物品總數量：<span class="font-semibold text-gray-800">{{ statistics.totals?.created || 0 }}</span> 件
                </div>
                <div class="space-y-3">
                    <!-- 1. 未到貨 -->
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">📦</span>
                            <span class="font-medium text-gray-700">未到貨</span>
                        </div>
                        <span class="text-xl font-bold text-gray-600">{{ statistics.status?.pre_arrival || 0 }}</span>
                    </div>

                    <!-- 2. 未使用 -->
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">📚</span>
                            <span class="font-medium text-gray-700">未使用</span>
                        </div>
                        <span class="text-xl font-bold text-yellow-600">{{ statistics.status?.unused || 0 }}</span>
                    </div>

                    <!-- 3. 使用中 -->
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">✅</span>
                            <span class="font-medium text-gray-700">使用中</span>
                        </div>
                        <span class="text-xl font-bold text-blue-600">{{ statistics.status?.in_use || 0 }}</span>
                    </div>

                    <!-- 4. 未使用就棄用 -->
                    <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">⚠️</span>
                            <span class="font-medium text-gray-700">未使用就棄用</span>
                        </div>
                        <span class="text-xl font-bold text-orange-600">{{ statistics.status?.unused_discarded || 0 }}</span>
                    </div>

                    <!-- 5. 使用後棄用 -->
                    <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">🗑️</span>
                            <span class="font-medium text-gray-700">使用後棄用</span>
                        </div>
                        <span class="text-xl font-bold text-purple-600">{{ statistics.status?.used_discarded || 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Value Statistics -->
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="text-lg font-semibold mb-2 text-gray-800">
                    💰 價值統計
                    <span class="text-sm font-normal text-gray-500">
                        ({{ getPeriodLabel() }}新增的物品)
                    </span>
                </h2>
                <div class="mb-4 text-sm text-gray-600">
                    {{ getPeriodLabel() }}新增物品總數量：<span class="font-semibold text-gray-800">{{ statistics.totals?.created || 0 }}</span> 件
                </div>
                <div class="space-y-3">
                    <!-- 總支出 -->
                    <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                        <span class="font-medium text-gray-700">總支出</span>
                        <span class="text-xl font-bold text-indigo-600">
                            ${{ formatNumber(statistics.value_stats?.total_expense || 0) }}
                        </span>
                    </div>

                    <!-- 有效支出 -->
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <span class="font-medium text-gray-700">有效支出</span>
                        <span class="text-xl font-bold text-green-600">
                            ${{ formatNumber(statistics.value_stats?.effective_expense || 0) }}
                        </span>
                    </div>

                    <!-- 支出效率 -->
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <span class="font-medium text-gray-700">支出效率</span>
                        <span class="text-xl font-bold text-blue-600">
                            {{ formatNumber(statistics.value_stats?.expense_efficiency || 0) }}%
                        </span>
                    </div>

                    <!-- 棄用物品平均使用成本 -->
                    <div v-if="statistics.value_stats?.discarded_cost_per_day > 0" class="flex items-center justify-between p-3 bg-purple-50 rounded-lg border-l-4 border-purple-400">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-700">棄用物品平均使用成本</span>
                            <span class="text-xs text-purple-600">💡</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold text-purple-600">
                                ${{ formatNumber(statistics.value_stats.discarded_cost_per_day) }}/天
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 尚未使用的物品 -->
            <div v-if="statistics.unused_items && statistics.unused_items.count > 0" class="bg-white rounded-lg shadow p-4">
                <h2 class="text-lg font-semibold mb-4 text-gray-800">
                    📚 尚未使用的物品
                    <span class="text-sm font-normal text-gray-500">
                        ({{ getPeriodLabel() }}新增的物品)
                    </span>
                </h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg mb-4">
                        <span class="font-medium text-gray-700">尚未使用的物品總數量</span>
                        <span class="text-xl font-bold text-yellow-600">
                            {{ statistics.unused_items.count || 0 }} 件
                        </span>
                    </div>
                    <h3 class="text-base font-semibold text-gray-700 mb-3">價值最高的前五名</h3>
                    <div
                        v-for="(data, index) in statistics.unused_items.top_five"
                        :key="data.item.id"
                        @click="goToItem(data.item.short_id)"
                        class="bg-white rounded-lg shadow-md p-4 flex items-center space-x-4 hover:shadow-lg transition-all cursor-pointer border-l-4 border-yellow-500"
                    >
                        <!-- Rank Badge -->
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-yellow-500 flex items-center justify-center text-white font-bold text-sm">
                            {{ index + 1 }}
                        </div>
                        
                        <!-- Item Image -->
                        <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-200">
                            <img
                                v-if="data.item.main_image"
                                :src="data.item.main_image.thumb_url"
                                :alt="data.item.name"
                                class="w-full h-full object-cover"
                                @error="$event.target.style.display='none'"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">
                                📦
                            </div>
                        </div>
                        
                        <!-- Item Info -->
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800 truncate">{{ data.item.name }}</div>
                            <div v-if="data.item.product?.name" class="text-sm text-gray-500 truncate">
                                {{ data.item.product.name }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                至今 {{ formatDays(data.days_unused || 0) }} 天
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="flex-shrink-0 text-right">
                            <div class="text-lg font-bold text-yellow-600">
                                ${{ formatNumber(data.item.price) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top 5 Most Expensive Items -->
            <div v-if="statistics.top_expensive && statistics.top_expensive.length > 0" class="bg-white rounded-lg shadow p-4">
                <h2 class="text-lg font-semibold mb-4 text-gray-800">
                    💎 價格最昂貴的前五名
                    <span class="text-sm font-normal text-gray-500">
                        ({{ getPeriodLabel() }}新增的物品)
                    </span>
                </h2>
                <div class="space-y-3">
                    <div
                        v-for="(item, index) in statistics.top_expensive"
                        :key="item.id"
                        @click="goToItem(item.short_id)"
                        class="bg-white rounded-lg shadow-md p-4 flex items-center space-x-4 hover:shadow-lg transition-all cursor-pointer border-l-4 border-orange-500"
                    >
                        <!-- Rank Badge -->
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white font-bold text-sm">
                            {{ index + 1 }}
                        </div>
                        
                        <!-- Item Image -->
                        <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-200">
                            <img
                                v-if="item.main_image"
                                :src="item.main_image.thumb_url"
                                :alt="item.name"
                                class="w-full h-full object-cover"
                                @error="$event.target.style.display='none'"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">
                                📦
                            </div>
                        </div>
                        
                        <!-- Item Info -->
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-800 truncate">{{ item.name }}</div>
                            <div v-if="item.product?.name" class="text-sm text-gray-500 truncate">
                                {{ item.product.name }}
                            </div>
                            <div v-if="item.status" class="text-xs mt-1">
                                <span :class="getStatusClass(item.status)">
                                    {{ getStatusLabel(item.status) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="flex-shrink-0 text-right">
                            <div class="text-lg font-bold text-orange-600">
                                ${{ formatNumber(item.price) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 已結案物品成本統計 -->
            <div v-if="statistics.discarded_cost_stats" class="bg-white rounded-lg shadow p-4">
                <h2 class="text-lg font-semibold mb-4 text-gray-800">
                    📊 已結案物品成本統計
                    <span class="text-sm font-normal text-gray-500">
                        ({{ getPeriodLabel() }}未使用棄用、已使用棄用的物品，平均每日成本)
                    </span>
                </h2>
                
                <!-- 平均每日成本 -->
                <div v-if="statistics.discarded_cost_stats.average_cost_per_day > 0" class="mb-4 p-3 bg-purple-50 rounded-lg border-l-4 border-purple-400">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-700">平均每日成本</span>
                        <span class="text-xl font-bold text-purple-600">
                            ${{ formatNumber(statistics.discarded_cost_stats.average_cost_per_day) }}/天
                        </span>
                    </div>
                </div>

                <!-- 每日成本最高的前五名 -->
                <div v-if="statistics.discarded_cost_stats.top_five && statistics.discarded_cost_stats.top_five.length > 0">
                    <h3 class="text-md font-semibold mb-3 text-gray-700">每日成本最高的前五名</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(data, index) in statistics.discarded_cost_stats.top_five"
                            :key="data.item.id"
                            @click="goToItem(data.item.short_id)"
                            class="bg-white rounded-lg shadow-md p-4 flex items-center space-x-4 hover:shadow-lg transition-all cursor-pointer border-l-4 border-purple-500"
                        >
                            <!-- Rank Badge -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold text-sm">
                                {{ index + 1 }}
                            </div>
                            
                            <!-- Item Image -->
                            <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-200">
                                <img
                                    v-if="data.item.main_image"
                                    :src="data.item.main_image.thumb_url"
                                    :alt="data.item.name"
                                    class="w-full h-full object-cover"
                                    @error="$event.target.style.display='none'"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">
                                    📦
                                </div>
                            </div>
                            
                            <!-- Item Info -->
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-800 truncate">{{ data.item.name }}</div>
                                <div v-if="data.item.product?.name" class="text-sm text-gray-500 truncate">
                                    {{ data.item.product.name }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    使用 {{ formatDays(data.usage_days) }} 天
                                </div>
                            </div>
                            
                            <!-- Cost Per Day -->
                            <div class="flex-shrink-0 text-right">
                                <div class="text-lg font-bold text-purple-600">
                                    ${{ formatNumber(data.cost_per_day) }}/天
                                </div>
                                <div class="text-xs text-gray-500">
                                    總價 ${{ formatNumber(data.item.price) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 使用中物品成本統計 -->
            <div v-if="statistics.in_use_cost_stats" class="bg-white rounded-lg shadow p-4">
                <h2 class="text-lg font-semibold mb-4 text-gray-800">
                    📊 使用中物品成本統計
                    <span class="text-sm font-normal text-gray-500">
                        ({{ getPeriodLabel() }}使用中的物品，計算至查詢當天)
                    </span>
                </h2>
                <div class="text-xs text-gray-400 mb-4">
                    查詢時間：{{ new Date().toLocaleString('zh-TW', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
                </div>
                
                <!-- 平均每日成本 -->
                <div v-if="statistics.in_use_cost_stats.average_cost_per_day > 0" class="mb-4 p-3 bg-blue-50 rounded-lg border-l-4 border-blue-400">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-700">平均每日成本</span>
                        <span class="text-xl font-bold text-blue-600">
                            ${{ formatNumber(statistics.in_use_cost_stats.average_cost_per_day) }}/天
                        </span>
                    </div>
                </div>

                <!-- 每日成本最高的前五名 -->
                <div v-if="statistics.in_use_cost_stats.top_five && statistics.in_use_cost_stats.top_five.length > 0">
                    <h3 class="text-md font-semibold mb-3 text-gray-700">每日成本最高的前五名</h3>
                    <div class="space-y-3">
                        <div
                            v-for="(data, index) in statistics.in_use_cost_stats.top_five"
                            :key="data.item.id"
                            @click="goToItem(data.item.short_id)"
                            class="bg-white rounded-lg shadow-md p-4 flex items-center space-x-4 hover:shadow-lg transition-all cursor-pointer border-l-4 border-blue-500"
                        >
                            <!-- Rank Badge -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-sm">
                                {{ index + 1 }}
                            </div>
                            
                            <!-- Item Image -->
                            <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-200">
                                <img
                                    v-if="data.item.main_image"
                                    :src="data.item.main_image.thumb_url"
                                    :alt="data.item.name"
                                    class="w-full h-full object-cover"
                                    @error="$event.target.style.display='none'"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-2xl">
                                    📦
                                </div>
                            </div>
                            
                            <!-- Item Info -->
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-800 truncate">{{ data.item.name }}</div>
                                <div v-if="data.item.product?.name" class="text-sm text-gray-500 truncate">
                                    {{ data.item.product.name }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    已使用 {{ formatDays(data.usage_days) }} 天
                                </div>
                            </div>
                            
                            <!-- Cost Per Day -->
                            <div class="flex-shrink-0 text-right">
                                <div class="text-lg font-bold text-blue-600">
                                    ${{ formatNumber(data.cost_per_day) }}/天
                                </div>
                                <div class="text-xs text-gray-500">
                                    總價 ${{ formatNumber(data.item.price) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../axios'

const router = useRouter()
const loading = ref(true)
const statistics = ref({})
const selectedPeriod = ref('week') // 預設為本週
const selectedYear = ref(new Date().getFullYear()) // 預設為今年

// 生成可選年份列表（過去 5 年到未來 1 年）
const availableYears = computed(() => {
    const currentYear = new Date().getFullYear()
    const years = []
    for (let i = currentYear - 5; i <= currentYear + 1; i++) {
        years.push(i)
    }
    return years.reverse() // 最新的年份在前
})

// 時間範圍選項（由小到大排序：本週、本月、近三個月、年度、全部）
const allPeriods = [
    { value: 'week', label: '本週' },
    { value: 'month', label: '本月' },
    { value: 'three_months', label: '近三個月' },
    { value: 'year', label: '年度' },
    { value: 'all', label: '全部' },
]

// 計算淨增加（購入 - 棄用）
const netChange = computed(() => {
    const created = statistics.value.totals?.created || 0
    const discarded = statistics.value.totals?.discarded || 0
    return created - discarded
})

// 計算平衡狀態
const balanceStatus = computed(() => {
    const created = statistics.value.totals?.created || 0
    const discarded = statistics.value.totals?.discarded || 0
    
    if (created === 0 && discarded === 0) {
        return 'perfect' // 都沒有，也算平衡
    }
    
    const diff = Math.abs(netChange.value)
    const max = Math.max(created, discarded)
    
    if (diff === 0) {
        return 'perfect' // 完全相等
    } else if (diff <= 2 || (max > 0 && diff / max <= 0.2)) {
        return 'good' // 差值很小或比例很小（20%以內）
    } else {
        return 'info' // 差值較大，但用正面的方式呈現
    }
})

// 平衡百分比（用於進度條）
const balancePercentage = computed(() => {
    const created = statistics.value.totals?.created || 0
    const discarded = statistics.value.totals?.discarded || 0
    
    if (created === 0 && discarded === 0) {
        return 100 // 都沒有，顯示滿格
    }
    
    const max = Math.max(created, discarded)
    const min = Math.min(created, discarded)
    
    if (max === 0) {
        return 100
    }
    
    // 計算平衡度：最小值/最大值 * 100
    // 如果相等則為 100%，差值越大百分比越低
    return Math.round((min / max) * 100)
})

// 平衡狀態文字（簡化為：增加、減少、接近平衡、完美平衡）
const balanceStatusText = computed(() => {
    const created = statistics.value.totals?.created || 0
    const discarded = statistics.value.totals?.discarded || 0
    
    if (netChange.value === 0) {
        return '完美平衡'
    }
    
    const diff = Math.abs(netChange.value)
    const max = Math.max(created, discarded)
    
    // 如果差值很小或比例很小（20%以內），視為接近平衡
    if (diff <= 2 || (max > 0 && diff / max <= 0.2)) {
        return '接近平衡'
    }
    
    // 否則根據正負值判斷增加或減少
    if (netChange.value > 0) {
        return '增加'
    } else {
        return '減少'
    }
})

// 平衡圖示
const balanceIcon = computed(() => {
    const status = balanceStatus.value
    if (status === 'perfect') return '✅'
    if (status === 'good') return '👍'
    return '💡'
})

// 平衡標題
const balanceTitle = computed(() => {
    const created = statistics.value.totals?.created || 0
    const discarded = statistics.value.totals?.discarded || 0
    
    if (netChange.value === 0) {
        return '完美平衡！'
    } else if (netChange.value > 0) {
        return `這段期間新增了 ${netChange.value} 件物品`
    } else {
        return `這段期間清理了 ${Math.abs(netChange.value)} 件物品`
    }
})

// 平衡訊息
const balanceMessage = computed(() => {
    const status = balanceStatus.value
    const created = statistics.value.totals?.created || 0
    const discarded = statistics.value.totals?.discarded || 0
    
    if (status === 'perfect') {
        return '保持房間物品數量穩定，維持良好的物品管理習慣！'
    } else if (status === 'good') {
        if (netChange.value > 0) {
            return '接近平衡狀態，繼續保持這個節奏！'
        } else {
            return '接近平衡狀態，斷捨離進行得很順利！'
        }
    } else {
        if (netChange.value > 0) {
            return `如果希望保持房間物品數量平衡，可以考慮適時清理閒置物品。`
        } else {
            return '正在進行斷捨離，如果需要的話可以補充必要的物品。'
        }
    }
})

// 處理時間範圍點擊
const handlePeriodClick = (periodValue) => {
    selectedPeriod.value = periodValue
    fetchStatistics()
}

const fetchStatistics = async () => {
    try {
        loading.value = true
        const params = { period: selectedPeriod.value }
        
        // 如果選擇了年份，傳遞年份參數
        if (selectedPeriod.value === 'year') {
            params.year = selectedYear.value
        }
        
        const res = await axios.get('/api/items/statistics/overview', { params })
        if (res.data.success) {
            statistics.value = res.data.data
        }
    } catch (error) {
        console.error('載入統計資料失敗:', error)
    } finally {
        loading.value = false
    }
}

const getPeriodLabel = () => {
    if (selectedPeriod.value === 'year') {
        return `${selectedYear.value} 年`
    }
    if (selectedPeriod.value === 'all') {
        return '全部'
    }
    const period = allPeriods.find(p => p.value === selectedPeriod.value)
    return period ? period.label : ''
}

const formatNumber = (num) => {
    if (!num && num !== 0) return '0'
    // 如果是小數，保留一位小數；否則不顯示小數
    const isDecimal = num % 1 !== 0
    return new Intl.NumberFormat('zh-TW', {
        minimumFractionDigits: isDecimal ? 1 : 0,
        maximumFractionDigits: isDecimal ? 1 : 0
    }).format(num)
}

const formatDateRange = (dateRange) => {
    if (!dateRange || !dateRange.start_formatted || !dateRange.end_formatted) {
        return '—'
    }
    return `${dateRange.start_formatted} - ${dateRange.end_formatted}`
}

const formatDays = (days) => {
    if (!days && days !== 0) return '0'
    return Number(days).toFixed(1)
}

const goToItem = (shortId) => {
    router.push(`/items/${shortId}`)
}

const getStatusLabel = (status) => {
    const labels = {
        'pre_arrival': '未到貨',
        'unused': '未使用',
        'in_use': '使用中',
        'unused_discarded': '未使用就棄用',
        'used_discarded': '使用後棄用',
    }
    return labels[status] || status
}

const getStatusClass = (status) => {
    const classes = {
        'pre_arrival': 'text-gray-600',
        'unused': 'text-yellow-600',
        'in_use': 'text-blue-600',
        'unused_discarded': 'text-orange-600',
        'used_discarded': 'text-purple-600',
    }
    return classes[status] || 'text-gray-600'
}


onMounted(() => {
    fetchStatistics()
})
</script>

