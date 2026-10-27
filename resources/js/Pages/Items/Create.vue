<template>
    <main class="create-page">
        <div v-if="isExternalFileDragging"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[var(--backdrop)] backdrop-blur-[2px] pointer-events-none">
            <div class="mx-6 w-full max-w-sm rounded-3xl border border-[var(--border-subtle)] bg-[var(--surface)] px-8 py-9 text-center   ring-1 ">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand-ink)] ring-1 ring-[var(--border-subtle)]">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H7.5M3 16.5l3.22-3.22a2.25 2.25 0 0 1 3.18 0l1.35 1.35 3.72-3.72a2.25 2.25 0 0 1 3.18 0L21 14.25M15.75 8.25h.008v.008h-.008V8.25Z" />
                    </svg>
                </div>
                <p class="text-lg font-semibold tracking-tight text-[var(--text)]">放開以上傳圖片</p>
                <p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">支援一次拖入多張圖片，最多 9 張</p>
            </div>
        </div>
        <header class="page-heading">
            <h1>新增物品</h1>
            <router-link to="/items" class="back-link">
                返回列表
            </router-link>
        </header>



<form ref="itemForm" @submit.prevent="submitForm(false)" @keydown="preventImplicitSubmit" @wheel.capture.passive="blurNumberOnWheel" class="item-form" :inert="isSubmitting" :aria-busy="isSubmitting">
    <div class="form-fields">
        <section class="form-section" aria-labelledby="basic-section-title">
        <h2 id="basic-section-title">基本資料與日期</h2>
        <div>
                <label for="create-name" class="block font-medium">名稱 <span class="text-[var(--danger)]">*</span></label>
                <input id="create-name" :aria-invalid="Boolean(formErrors.name)" :aria-describedby="formErrors.name ? 'create-name-error' : undefined" v-model="form.name" type="text"
                    :class="['w-full p-2 border rounded', formErrors.name ? 'border-[var(--danger)]' : '']"
                    required @keydown.enter.prevent />
                <p id="create-name-error" v-if="formErrors.name" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.name }}</p>
            </div>
        <div>
                <label for="date-purchased_at" class="block font-medium">購買日期 <span class="text-[var(--danger)]">*</span></label>
                <DateInput label="購買日期" id="date-purchased_at" v-model="form.purchased_at" @draft-change="value => setDateDraft('purchased_at', value)"
                    class="w-full min-w-0" :invalid="Boolean(formErrors.purchased_at)" :error-message="formErrors.purchased_at || ''"
                    :min="getDateMin(form, 'purchased_at')" :max="purchaseDateMax" required />
                <p v-if="formErrors.purchased_at" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.purchased_at }}</p>
            </div>
    <div>
                <div class="arrival-heading">
                    <label for="date-received_at" class="block font-medium">到貨日期</label>
                    <label class="same-day-toggle"><input v-model="receivedWithPurchase" type="checkbox" />到貨日同購買日</label>
                </div>
                <DateInput label="到貨日期" id="date-received_at" v-model="form.received_at" @draft-change="value => setDateDraft('received_at', value)" :disabled="receivedWithPurchase"
                    class="w-full min-w-0" :invalid="Boolean(formErrors.received_at)" :error-message="formErrors.received_at || ''"
                    :min="getDateMin(form, 'received_at')" :max="getDateMax(form, 'received_at')" />
                <p v-if="formErrors.received_at" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.received_at }}</p>
            </div>
<div>
                <div class="expiry-heading">
                    <label for="date-expiration_date" class="block font-medium">有效期限</label>
                    <button type="button" @click="showManufactureDateModal = true" class="text-action">使用製造日期換算</button>
                </div>
                <DateInput label="有效期限" id="date-expiration_date" v-model="form.expiration_date" @draft-change="value => setDateDraft('expiration_date', value)"
                    class="w-full min-w-0" :invalid="Boolean(formErrors.expiration_date)" :error-message="formErrors.expiration_date || ''" max="9999-12-31" />
                <p v-if="formErrors.expiration_date" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.expiration_date }}</p>
            </div>
</section>
        <section class="form-section quantity-card" aria-labelledby="quantity-section-title">
            <h2 id="quantity-section-title">數量與金額</h2>
            <div class="amount-equation" role="group" :aria-label="creationSummary.rounded ? '數量乘單價約等於總金額' : '數量乘單價等於總金額'" aria-describedby="amount-equation-help">
                <div class="equation-field">
                    <label for="create-quantity"><span>數量 <span class="text-[var(--danger)]">*</span></span></label>
                    <input id="create-quantity" :value="form.quantity" type="text" inputmode="numeric" autocomplete="off" required
                        @input="quantityInput.onInput" @compositionstart="quantityInput.onCompositionStart" @compositionend="quantityInput.onCompositionEnd"
                        :aria-invalid="quantityLimitExceeded || creationSummary.field === 'quantity' || Boolean(formErrors.quantity)" :aria-describedby="['create-quantity-help', formErrors.quantity && 'create-quantity-error'].filter(Boolean).join(' ')" />
                    <p id="create-quantity-help" :class="['quantity-help', { 'quantity-help--error': quantityLimitExceeded }]">單次最多 {{ maxQuantity }} 件。</p>
                    <p v-if="formErrors.quantity" id="create-quantity-error" class="quantity-error" role="alert">{{ formErrors.quantity }}</p>
                </div>
                <span class="equation-symbol" aria-hidden="true">×</span>
                <div class="equation-field">
                    <div class="equation-label">
                        <label class="price-choice"><input name="price-mode" :checked="priceMode === 'unit'" type="radio" value="unit" @change="switchPriceMode('unit')" /><span><span class="sr-only">填寫</span>單價</span></label>
                        <span v-if="priceMode !== 'unit'" class="input-state">自動計算</span>
                    </div>
                    <label class="sr-only" :for="priceMode === 'unit' ? 'create-price' : 'calculated-unit-price'">單價</label>
                    <input :id="priceMode === 'unit' ? 'create-price' : 'calculated-unit-price'" type="text" inputmode="decimal" autocomplete="off"
                        :disabled="priceMode !== 'unit'" :value="priceMode === 'unit' ? form.price : creationSummary.valid && creationSummary.unitPrice != null ? formatAmount(creationSummary.unitPrice) : ''"
                        :aria-invalid="priceMode === 'unit' && Boolean(formErrors.price)" :aria-describedby="formErrors.price ? 'create-price-error' : 'amount-equation-help'"
                        @input="amountInput.onInput" @compositionstart="amountInput.onCompositionStart" @compositionend="amountInput.onCompositionEnd" />
                </div>
                <span class="equation-symbol" aria-hidden="true">{{ creationSummary.rounded ? '≈' : '＝' }}</span>
                <div class="equation-field">
                    <div class="equation-label">
                        <label class="price-choice"><input name="price-mode" :checked="priceMode === 'total'" type="radio" value="total" @change="switchPriceMode('total')" /><span><span class="sr-only">填寫</span>總金額</span></label>
                        <span v-if="priceMode !== 'total'" class="input-state">自動計算</span>
                    </div>
                    <label class="sr-only" :for="priceMode === 'total' ? 'create-price' : 'calculated-total-price'">總金額</label>
                    <input :id="priceMode === 'total' ? 'create-price' : 'calculated-total-price'" type="text" inputmode="decimal" autocomplete="off"
                        :disabled="priceMode !== 'total'" :value="priceMode === 'total' ? totalAmount : displayedTotal != null ? formatAmount(displayedTotal) : ''"
                        :aria-invalid="priceMode === 'total' && Boolean(formErrors.price)" :aria-describedby="formErrors.price ? 'create-price-error' : 'amount-equation-help'"
                        @input="amountInput.onInput" @compositionstart="amountInput.onCompositionStart" @compositionend="amountInput.onCompositionEnd" />
                </div>
            </div>
            <div class="equation-help">
                <p id="amount-equation-help">每件物品以單價記錄成本；總金額僅用來換算單價。</p>
                <p v-if="priceMode === 'total'">例如：5 件共 450，換算後每件單價為 90。</p>
                <p v-if="creationSummary.rounded" class="rounding-note">單價以兩位小數顯示，原總金額維持 {{ formatAmount(displayedTotal) }}；儲存合計為 {{ formatAmount(creationSummary.totalPrice) }}。</p>
                <p v-if="formErrors.price" id="create-price-error" class="equation-error" role="alert">{{ formErrors.price }}</p>
            </div>
        </section>
        <section class="form-section" aria-labelledby="photos-section-title">
            <div class="photo-heading">
                <h2 id="photos-section-title">圖片 <span class="photo-count">（{{ uploadList.length }}/9）</span></h2>
                <AppButton variant="secondary" @click="showMediaLibrary = true">從媒體櫃選擇</AppButton>
            </div>
<div>

                <p v-if="imageFeedback" class="image-feedback" role="status">{{ imageFeedback }}</p>
                <p class="image-help">最多 9 張。照片上傳完成後，按儲存才會關聯到物品；移除只會從本次清單取消。</p>
                <p v-if="formErrors.images" class="text-sm text-[var(--danger)] mt-1 mb-2">{{ formErrors.images }}</p>
                <p v-if="uploadList.length > 1" class="text-xs text-[var(--text-muted)] mb-2">
                    短按查看大圖，長按拖曳排序。
                </p>
                <p class="sr-only" role="status">{{ reorderNotice }}</p>
                <div ref="photoGrid" class="photo-grid">
                    <div v-for="(item, index) in uploadList" :key="item.id"
                        :data-photo-id="item.id" class="photo-tile relative aspect-square border border-[var(--border-subtle)] rounded bg-[var(--surface)] overflow-visible"
                        draggable="false">
                        <button v-if="item.preview" type="button" class="photo-preview-trigger" draggable="false" @contextmenu.prevent @dragstart.prevent
                            :aria-label="`查看第 ${index + 1} 張大圖，長按或使用左右方向鍵排序`"
                            @click="onPreviewClick(item, $event)" @keydown="movePhotoWithKeyboard(item, $event)">
                            <img :src="item.preview" draggable="false" class="photo-preview w-full h-full object-contain"
                                :class="{ 'photo-preview--pending': ['preparing', 'waiting', 'uploading', 'processing'].includes(item.status) }"
                                :alt="`${form.name || '未命名物品'} - 預覽圖片 ${index + 1}`" />
                        </button>
                        <div v-else class="photo-placeholder" aria-hidden="true"></div>
                        <button type="button" @click.stop="removeImage(index)" @pointerdown.stop
                            class="photo-remove" :aria-label="`移除第 ${index + 1} 張圖片`">
                            <span class="photo-remove-badge"><AppIcon name="close" :size="16" /></span>
                        </button>
                        <div v-if="item.status === 'uploading'" class="absolute bottom-0 left-0 w-full h-1 bg-[var(--disabled)]">
                            <div class="bg-[var(--brand)] h-full" :style="{ width: item.progress + '%' }"></div>
                        </div>
                        <button v-if="item.status === 'error' && item.file" type="button" class="photo-retry" :aria-label="`重試第 ${index + 1} 張：${item.errorMessage}`" :title="item.errorMessage" @click.stop="retryImage(item)">重試上傳</button>
                        <span v-if="['preparing', 'waiting', 'uploading', 'processing'].includes(item.status)" class="photo-state">
                            <span v-if="['preparing', 'processing'].includes(item.status)" class="photo-spinner" aria-hidden="true"></span>
                            <AppIcon v-else :name="item.status === 'waiting' ? 'clock' : 'upload'" :size="20" />
                            <span class="sr-only">{{ item.status === 'uploading' ? `上傳 ${item.progress}%` : item.status === 'processing' ? '處理中' : item.status === 'preparing' ? '準備中' : '等待上傳' }}</span>
                        </span>
                    </div>
                    <!-- +加入照片按鈕（灰色系，與 input 風格一致） -->
                    <button type="button" v-if="uploadList.length < 9"
                        class="photo-add relative aspect-square border-2 border-dashed border-[var(--border-control)] flex items-center justify-center cursor-pointer bg-[var(--surface)]"
                        @click="fileInput.click()" @dragover.prevent @drop.prevent.stop="handleDrop">
                        <span class="text-[var(--text-muted)] text-sm">加入照片</span>
                    </button>
                    <input type="file" accept="image/*,.dng,.heic,.heif" multiple class="hidden" ref="fileInput"
                        aria-label="選擇物品照片" @change="handleFileSelect" />
                </div>
                <ul v-if="uploadList.some(image => image.status === 'error')" class="image-errors" aria-label="照片上傳失敗">
                    <template v-for="(image, index) in uploadList" :key="image.id">
                        <li v-if="image.status === 'error'">第 {{ index + 1 }} 張{{ image.name ? `（${image.name}）` : '' }}：{{ image.errorMessage || '上傳失敗，請重試。' }}</li>
                    </template>
                </ul>
            </div>
</section>
        <section class="form-section" aria-labelledby="description-section-title"><h2 id="description-section-title">描述與位置</h2>
<div>
                <label for="create-description" class="block font-medium">描述</label>
                <textarea id="create-description" :aria-invalid="Boolean(formErrors.description)" :aria-describedby="formErrors.description ? 'create-description-error' : undefined" v-model="form.description"
                    :class="['w-full p-2 border rounded', formErrors.description ? 'border-[var(--danger)]' : '']"
                    placeholder="可輸入多行描述" rows="4"></textarea>
                <p id="create-description-error" v-if="formErrors.description" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.description }}</p>
            </div>
<div>
                <label for="create-location" class="block font-medium">位置</label>
                <input id="create-location" :aria-invalid="Boolean(formErrors.location)" :aria-describedby="formErrors.location ? 'create-location-error' : undefined" v-model="form.location" type="text"
                    :class="['w-full p-2 border rounded', formErrors.location ? 'border-[var(--danger)]' : '']"
                    @keydown.enter.prevent />
                <p id="create-location-error" v-if="formErrors.location" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.location }}</p>
            </div>
</section>
        <section class="form-section" aria-labelledby="other-dates-title"><h2 id="other-dates-title">其他日期</h2>
<div>
                <label for="date-used_at" class="block font-medium">開始使用日期</label>
                <DateInput label="開始使用日期" id="date-used_at" v-model="form.used_at" @draft-change="value => setDateDraft('used_at', value)"
                    class="w-full min-w-0" :invalid="Boolean(formErrors.used_at)" :error-message="formErrors.used_at || ''"
                    :min="getDateMin(form, 'used_at')" :max="getDateMax(form, 'used_at')" />
                <p v-if="formErrors.used_at" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.used_at }}</p>
            </div>
<div>
                <label for="date-discarded_at" class="block font-medium">報廢日期</label>
                <DateInput label="報廢日期" id="date-discarded_at" v-model="form.discarded_at" @draft-change="value => setDateDraft('discarded_at', value)"
                    class="w-full min-w-0" :invalid="Boolean(formErrors.discarded_at)" :error-message="formErrors.discarded_at || ''"
                    :min="getDateMin(form, 'discarded_at')" :max="getDateMax(form, 'discarded_at')" />
                <p v-if="formErrors.discarded_at" class="text-sm text-[var(--danger)] mt-1">{{ formErrors.discarded_at }}</p>
            </div>
</section>
        <section class="form-section product-section" aria-labelledby="product-section-title">
        <h2 id="product-section-title">關聯產品</h2>
        <div class="supplementary-content">

        <!-- 產品選擇 Multiselect -->
        <div class="space-y-2">
            <label for="create-product" class="block font-medium">選擇產品</label>
            <Multiselect v-bind="multiselectLocale" id="create-product" v-model="selectedProduct" :options="products" :searchable="true"
                :custom-label="option => option.name" :track-by="'id'" placeholder="請輸入或選擇產品"
                :internal-search="false" @search-change="searchProduct" @select="onProductSelect">
                <template #noOptions>請輸入產品名稱搜尋。</template>
                <template #noResult>找不到符合的產品，請嘗試其他關鍵字。</template>
            </Multiselect>
            <p class="product-link-help text-sm text-[var(--text-muted)]">可直接新增物品，也可關聯產品帶入資料。</p>
        </div>

        <!-- 如果選到 isNew，顯示建立表單 -->
        <AppModal :model-value="creatingProduct" title="建立新產品" :busy="isCreatingProduct" @update:model-value="cancelCreateProduct">
        <div class="space-y-2"><label for="new-product-name" class="block font-medium">產品名稱 <span class="text-[var(--danger)]">*</span></label>
<input id="new-product-name" aria-required="true" v-model="newProduct.name" type="text" class="w-full p-2 border rounded" placeholder="產品名稱" />
<label for="new-product-brand" class="block font-medium">品牌</label>
<input id="new-product-brand" v-model="newProduct.brand" type="text" class="w-full p-2 border rounded" placeholder="品牌" />
<div class="space-y-2">
                <label for="new-product-category" class="block font-medium">分類</label>
                <Multiselect v-bind="multiselectLocale" id="new-product-category" v-model="newProduct.category" :options="categories" :searchable="true"
                    :custom-label="opt => opt.name" :track-by="'id'" placeholder="選擇分類"
                    :allow-empty="true" :close-on-select="true"
                    @search-change="onSearch" @select="onSelect">
                    <template #noOptions>目前沒有分類，可輸入名稱建立。</template>
                    <template #noResult>找不到符合的分類，請嘗試其他關鍵字。</template>
                </Multiselect>
            </div>
<label for="new-product-model" class="block font-medium">型號</label>
<input id="new-product-model" v-model="newProduct.model" type="text" class="w-full p-2 border rounded" placeholder="型號" />
<label for="new-product-spec" class="block font-medium">規格</label>
<input id="new-product-spec" v-model="newProduct.spec" type="text" class="w-full p-2 border rounded" placeholder="規格（如顏色、容量等）" />
<div class="space-y-1">
                <label for="new-product-barcode" class="block font-medium">條碼</label>
                <div class="flex gap-2 items-center">
                    <input id="new-product-barcode" v-model="newProduct.barcode" type="text" placeholder="輸入或掃描條碼"
                        class="flex-1 p-2 border rounded" />
                    <button type="button" @click="startBarcodeScan('productBarcode')"
                        class="text-[var(--brand-ink)] hover:text-[var(--brand-hover)] underline text-sm whitespace-nowrap">
                        掃描
                    </button>
                </div>
            </div>
<div v-if="showScanner"
                class="fixed inset-0 z-50 bg-[var(--text)] overflow-hidden scanner-container">
                <div class="w-full h-full relative">
                    <div id="scanner" class="scanner-fullscreen"></div>
                    <!-- 自定義掃描框指示器 -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
                        <div class="border-2 border-white rounded-lg"
                            style="width: 80%; max-width: 500px; aspect-ratio: 2.5/1; position: relative;">
                            <!-- 四個角的指示器 -->
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-[var(--success)] rounded-tl-lg"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-[var(--success)] rounded-tr-lg"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-[var(--success)] rounded-bl-lg"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-[var(--success)] rounded-br-lg"></div>
                            <!-- 檢測到條碼時的綠色覆蓋層 -->
                            <div v-if="barcodeDetected"
                                class="absolute inset-0 bg-[var(--success-soft)] rounded-lg flex items-center justify-center">
                                <div class="bg-[var(--success)] text-[var(--on-brand)] px-4 py-2 rounded-lg font-semibold">
                                    已檢測到條碼
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 flex gap-2 z-20">
                        <button type="button" @click="stopScanner"
                            class="px-4 py-2 bg-[var(--danger)] text-[var(--on-brand)] rounded-lg hover:bg-[var(--danger)] ">
                            關閉
                        </button>
                    </div>
                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-[var(--on-brand)] text-center z-20">
                        <p class="text-lg font-semibold mb-2">將條碼對準掃描框</p>
                        <p class="text-sm text-[var(--text-muted)]">請保持條碼水平對齊</p>
                    </div>
                </div>
            </div></div>
        <template #footer>
            <AppButton variant="secondary" :disabled="isCreatingProduct" @click="cancelCreateProduct">取消</AppButton>
            <AppButton :disabled="isCreatingProduct" :aria-busy="isCreatingProduct" @click="confirmCreateProduct">{{ isCreatingProduct ? '建立中…' : '建立產品' }}</AppButton>
        </template>
    </AppModal>

        <!-- 成功選到產品後顯示卡片 -->
        <div v-if="selectedProduct && !creatingProduct"
            class="bg-[var(--surface)] border rounded p-4 mt-4  space-y-1 text-sm text-[var(--text-muted)]">
            <div class="text-lg font-bold">{{ selectedProduct.name }}</div>

            <div v-if="selectedProduct.brand">品牌：{{ selectedProduct.brand }}</div>
            <div v-if="selectedProduct.category">分類：{{ selectedProduct.category.name }}</div>
            <div v-if="selectedProduct.model">型號：{{ selectedProduct.model }}</div>
            <div v-if="selectedProduct.spec">規格：{{ selectedProduct.spec }}</div>
            <div v-if="selectedProduct.barcode">條碼：{{ selectedProduct.barcode }}</div>
            <div>目前已有物品數量：{{ selectedProduct.owned_items_count ?? 0 }}</div>
        </div>

        </div>
    </section>
    </div>
    <aside class="form-section summary-panel" aria-labelledby="summary-panel-title">
        <h2 id="summary-panel-title">新增前確認</h2>
        <div class="summary-item">
            <div class="summary-thumbnail">
                <img v-if="uploadList[0]?.preview && !summaryImageFailed" :src="uploadList[0].preview" alt="" width="64" height="64" @error="summaryImageFailed = true" />
                <AppIcon v-else name="package" :size="24" />
            </div>
            <p class="summary-name">{{ form.name || '尚未填寫名稱' }}</p>
        </div>
        <dl class="summary-dates">
            <div><dt>購買日期</dt><dd>{{ form.purchased_at || '未填寫' }}</dd></div>
            <div><dt>到貨日期</dt><dd>{{ form.received_at || '未填寫' }}</dd></div>
            <div><dt>有效期限</dt><dd>{{ form.expiration_date || '未填寫' }}</dd></div>
            <div v-if="selectedProduct && !selectedProduct.isNew"><dt>關聯產品</dt><dd>{{ selectedProduct.name }}</dd></div>
        </dl>
        <div class="creation-summary" role="status" aria-live="polite" aria-atomic="true">
            <template v-if="creationSummary.valid">
                <p class="creation-count">將建立 <strong>{{ creationSummary.quantity }}</strong> 筆獨立物品</p>
                <dl><div><dt>每件單價</dt><dd>{{ formatAmount(creationSummary.unitPrice) }}</dd></div><div><dt>合計金額</dt><dd>{{ formatAmount(creationSummary.totalPrice) }}</dd></div></dl>
                <p v-if="creationSummary.rounded" class="rounding-note">輸入總金額為 {{ formatAmount(creationSummary.enteredTotal) }}；換算單價至小數兩位，儲存後合計為 {{ formatAmount(creationSummary.totalPrice) }}。</p>
            </template>
            <p v-else class="summary-error">{{ creationSummary.message }}</p>
        </div>
<div v-if="creationSummary.valid && requiresQuantityConfirmation(creationSummary.quantity)"  :class="['quantity-confirmation', { 'quantity-confirmation--confirmed': bulkQuantityConfirmed }]">
            <label for="confirm-quantity">輸入 {{ creationSummary.quantity }}，確認新增件數</label>
            <input id="confirm-quantity" :value="confirmedQuantity" @input="confirmationInput.onInput" @compositionstart="confirmationInput.onCompositionStart" @compositionend="confirmationInput.onCompositionEnd" type="text" inputmode="numeric" autocomplete="off" :aria-invalid="Boolean(formErrors.confirmation)" aria-describedby="confirm-quantity-help" />
            <p id="confirm-quantity-help" role="status" aria-atomic="true">{{ bulkQuantityConfirmed ? '件數已確認' : formErrors.confirmation || '' }}</p>
        </div>
<div class="form-actions">
            <AppButton type="submit" :disabled="saveDisabled" :aria-describedby="saveBlockReason ? 'save-block-reason' : undefined" :aria-busy="isSubmitting">{{ isSubmitting ? '儲存中…' : '儲存' }}</AppButton>
            <AppButton variant="secondary" :disabled="saveDisabled" :aria-describedby="saveBlockReason ? 'save-block-reason' : undefined" @click="submitForm(true)">儲存並繼續新增</AppButton>
        </div>
        <p id="save-block-reason" class="sr-only">{{ saveBlockReason }}</p>
    </aside>
    <AppModal :model-value="Boolean(previewPhoto)" title="圖片預覽" wide @update:model-value="previewPhoto = null">
        <img v-if="previewPhoto" :src="previewPhoto.preview_url || previewPhoto.url || previewPhoto.preview" :alt="form.name || '物品照片'" class="photo-full-preview" />
    </AppModal>
    <AppModal v-model="showMediaLibrary" title="選擇圖片（媒體櫃）" wide>
        <p class="image-help">已加入本次物品的照片會自動排除，避免重複選取。</p>
        <div v-if="mediaLibraryError" role="alert" class="media-library-error"><p>{{ mediaLibraryError }}</p><AppButton variant="secondary" @click="loadMediaLibrary">重試</AppButton></div>
        <div v-else-if="loadingMediaLibrary" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--brand)]"></div>
                            <p class="mt-3 text-sm text-[var(--text-muted)]">載入中...</p>
                        </div>
<div v-else-if="availableMediaImages.length === 0" class="text-center py-8">
                            <p class="text-[var(--text-muted)]">目前沒有可加入的圖片，已選照片不會重複顯示。</p>
                        </div>
<div v-else class="grid grid-cols-4 gap-2">
                            <button type="button" v-for="image in availableMediaImages" :key="image.uuid" :aria-pressed="selectedMediaImages.includes(image.uuid)" :aria-label="`選擇圖片 ${image.image_path}`"
                                class="relative aspect-square border-2 rounded cursor-pointer transition-all"
                                :class="selectedMediaImages.includes(image.uuid)
                                    ? 'border-[var(--brand)] bg-[var(--brand-soft)]'
                                    : 'border-[var(--border-control)] hover:border-[var(--brand-ink)]'"
                                @click="toggleMediaImage(image)">
                                <img :src="image.thumb_url" :alt="image.image_path"
                                    class="w-full h-full object-cover rounded" />
                                <div v-if="selectedMediaImages.includes(image.uuid)"
                                    class="absolute top-1 right-1 bg-[var(--brand)] text-[var(--on-brand)] rounded-full w-6 h-6 flex items-center justify-center text-sm"><AppIcon name="check" /></div>
                            </button>
                        </div>
        <template #footer>
            <AppButton variant="secondary" @click="showMediaLibrary = false">取消</AppButton>
            <AppButton :disabled="loadingMediaLibrary || Boolean(mediaLibraryError) || !selectedMediaImages.length" @click="addSelectedMediaImages">確認選擇（{{ selectedMediaImages.length }}）</AppButton>
        </template>
    </AppModal>
<div v-if="showManufactureDateModal"
                class="fixed top-0 left-0 w-screen h-screen bg-[var(--backdrop)] flex items-center justify-center z-50">
                <div class="bg-[var(--surface)] p-6 rounded-lg w-full max-w-sm max-h-[90dvh] overflow-y-auto mx-4 ">
                    <h3 class="text-lg font-semibold mb-4">製造日期換算有效期限</h3>

                    <div class="space-y-4">
                        <div>
                            <label for="manufacture-date" class="block text-sm font-medium mb-1">製造日期</label>
                            <DateInput label="製造日期" id="manufacture-date" v-model="manufactureDate" @draft-change="value => setDateDraft('manufactureDate', value)" class="w-full min-w-0" />
                        </div>

                        <div>
                            <label for="expiration-length" class="block text-sm font-medium mb-1">有效期限長度</label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <input id="expiration-length" v-model.number="expirationValue" type="number" min="0"
                                        class="w-full p-2 border rounded" placeholder="請輸入數字" />
                                </div>
                                <div class="w-24">
                                    <select aria-label="有效期限單位" v-model="expirationUnit" class="w-full p-2 border rounded">
                                        <option value="years">年</option>
                                        <option value="months">月</option>
                                        <option value="days">日</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-sm text-[var(--text-muted)]">
                            計算結果：{{ calculatedExpirationDate || '請輸入製造日期' }}
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="showManufactureDateModal = false"
                            class="px-4 py-2 border rounded hover:bg-[var(--brand-soft)]">
                            取消
                        </button>
                        <button type="button" @click="applyCalculatedDate"
                            class="px-4 py-2 bg-[var(--brand)] text-[var(--on-brand)] rounded hover:bg-[var(--brand-hover)]"
                            :disabled="!calculatedExpirationDate">
                            套用
                        </button>
                    </div>
                </div>
            </div>
</form>
</main>
</template>

<script setup>
import AppIcon from '../../Components/AppIcon.vue'
import AppButton from '../../Components/AppButton.vue'
import AppModal from '../../Components/AppModal.vue'
import { useSortablePhotos } from '../../composables/useSortablePhotos'
import { usePurchaseArrivalLink } from '../../composables/usePurchaseArrivalLink'
import { createNumericInputHandlers } from '../../utils/numericInput'
import { useItemPricing } from '../../composables/useItemPricing'
import DateInput from '../../Components/DateInput.vue'
import { getItemSaveBlockReason } from '../../utils/itemSaveState'
import { requiresQuantityConfirmation, isQuantityConfirmed } from '../../utils/itemCreationSummary'
import { getTodayDate, getDateMin, getDateMax } from '../../utils/itemDates'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import { ref, onMounted, onBeforeUnmount, nextTick, watchEffect, computed, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { createUnsavedChangesGuard } from '../../utils/unsavedChanges'
import { preventImplicitSubmit } from '../../utils/formKeyboard'
import { getAvailableItemMedia, getNewItemMedia, getUniqueItemImagePayload, imageUuidKey } from '../../utils/itemImageSelection'
import { formatDateText, parseDateText, getDateTextError } from '../../utils/dateTextInput'
import axios from '../../axios'
import { Html5Qrcode } from 'html5-qrcode'
import Swal from 'sweetalert2'
import { isDngFile, isSupportedImageFile, prepareImageForUpload } from '../../utils/imageUpload'

const multiselectLocale = {
    selectLabel: '按 Enter 選取',
    selectedLabel: '已選取',
    deselectLabel: '按 Enter 移除',
    selectGroupLabel: '按 Enter 選取群組',
    deselectGroupLabel: '按 Enter 取消選取群組',
    tagPlaceholder: '按 Enter 建立',
    limitText: count => `另有 ${count} 個選項`,
}
const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const creating = ref(false)
const creatingCategory = ref(false)

const router = useRouter()
let pageActive = true

// 日期上限（今天後一個月）

const showScanner = ref(false)
const isSubmitting = ref(false)
const scanTarget = ref(null) // 記錄掃描目標：'productBarcode' 或 'itemBarcode'
const barcodeDetected = ref(false) // 是否檢測到條碼

const fileInput = ref(null)
const uploadList = ref([])
const imageFeedback = ref('')
const uploadControllers = new Map()
let uploadQueueRunning = false
const summaryImageFailed = ref(false)
watch(() => uploadList.value[0]?.preview, () => { summaryImageFailed.value = false })
let uploadId = 0
const isExternalFileDragging = ref(false)
let externalDragDepth = 0
const previewPhoto = ref(null)
const { photoGrid, reorderNotice, onPreviewClick, movePhotoWithKeyboard } = useSortablePhotos(uploadList, item => { previewPhoto.value = item })

// 媒體櫃相關
const showMediaLibrary = ref(false)
const mediaLibraryImages = ref([])
const loadingMediaLibrary = ref(false)
const selectedMediaImages = ref([])
const mediaLibraryError = ref('')
const availableMediaImages = computed(() => getAvailableItemMedia(mediaLibraryImages.value, uploadList.value))
watch(availableMediaImages, images => {
    const available = new Set(images.map(image => imageUuidKey(image.uuid)))
    selectedMediaImages.value = selectedMediaImages.value.filter(uuid => available.has(imageUuidKey(uuid)))
})

const handleFileSelect = async (e) => {
    const files = Array.from(e.target.files)
    const maxImages = 9

    if (uploadList.value.length + files.length > maxImages) {
        alert(`最多只能上傳 ${maxImages} 張圖片，目前已上傳 ${uploadList.value.length} 張`)
        e.target.value = ''
        return
    }

    await prepareUpload(files)
    e.target.value = ''
}

const handleDrop = async (e) => {
    externalDragDepth = 0
    isExternalFileDragging.value = false
    const files = Array.from(e.dataTransfer.files)
        .filter(isSupportedImageFile)
        .sort((a, b) => a.name.localeCompare(b.name, 'zh-TW', {
            numeric: true,
            sensitivity: 'base',
        }))
    const maxImages = 9

    if (uploadList.value.length + files.length > maxImages) {
        alert(`最多只能上傳 ${maxImages} 張圖片，目前已上傳 ${uploadList.value.length} 張`)
        return
    }

    await prepareUpload(files)
}

const isFileDrag = event => Array.from(event.dataTransfer?.types || []).includes('Files')

const handleWindowDragEnter = (event) => {
    if (!isFileDrag(event)) return
    event.preventDefault()
    externalDragDepth += 1
    isExternalFileDragging.value = true
}

const handleWindowDragOver = (event) => {
    if (!isFileDrag(event)) return
    event.preventDefault()
    event.dataTransfer.dropEffect = 'copy'
}

const handleWindowDragLeave = (event) => {
    if (!isFileDrag(event)) return
    externalDragDepth = Math.max(0, externalDragDepth - 1)
    if (externalDragDepth === 0) isExternalFileDragging.value = false
}

const handleWindowDrop = async (event) => {
    if (!isFileDrag(event)) return
    event.preventDefault()
    externalDragDepth = 0
    isExternalFileDragging.value = false
    await handleDrop(event)
}

const prepareUpload = async (files) => {
    imageFeedback.value = ''
    for (const sourceFile of files) {
        if (!pageActive) break
        if (uploadList.value.length >= 9) { imageFeedback.value = '已達 9 張上限，未加入剩餘照片。'; break }
        const id = uploadId++
        const record = {
            id,
            name: sourceFile.name,
            file: null,
            preview: '',
            progress: 0,
            status: 'preparing',
            url: '',
            thumb_url: '',
            preview_url: ''
        }
        uploadList.value.push(record)
        const item = uploadList.value[uploadList.value.length - 1]

        try {
            const prepared = await prepareImageForUpload(sourceFile)
            if (!pageActive || !uploadList.value.includes(item)) continue
            item.file = prepared
            item.preview = URL.createObjectURL(item.file)
            item.status = 'waiting'
            item.convertedFromDng = isDngFile(sourceFile)
            startUploadQueue()
        } catch (error) {
            uploadList.value = uploadList.value.filter(upload => upload.id !== id)
            console.error('圖片準備失敗', error)
            alert(`${sourceFile.name} 無法處理：${error.message}`)
        }
    }
    startUploadQueue()
}

const startUploadQueue = async () => {
    if (uploadQueueRunning || !pageActive) return
    uploadQueueRunning = true
    try {
        while (pageActive) {
            const item = uploadList.value.find(image => image.status === 'waiting')
            if (!item) break
            item.status = 'uploading'
            item.errorMessage = ''
            const controller = new AbortController()
            uploadControllers.set(item.id, controller)
            const formData = new FormData()
            formData.append('image', item.file)
            try {
                const res = await axios.post('/api/item-images', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    signal: controller.signal,
                    onUploadProgress: event => {
                        if (!event.total || !uploadList.value.includes(item)) return
                        item.progress = Math.min(100, Math.round(event.loaded * 100 / event.total))
                        if (event.loaded >= event.total) item.status = 'processing'
                    },
                })
                if (!pageActive || !uploadList.value.includes(item)) continue
                const uuid = res.data.data.uuid
                item.uuid = uuid
                item.statusFromApi = 'new'
                item.status = 'done'
                item.progress = 100
                item.file = null
                const seen = new Set()
                uploadList.value = uploadList.value.filter(image => {
                    const key = imageUuidKey(image.uuid)
                    if (!key) return true
                    if (seen.has(key)) {
                        if (image.preview?.startsWith('blob:')) URL.revokeObjectURL(image.preview)
                        imageFeedback.value = '已略過重複圖片，每張照片只加入一次。'
                        return false
                    }
                    seen.add(key)
                    return true
                })
            } catch (error) {
                if (error.code !== 'ERR_CANCELED' && pageActive && uploadList.value.includes(item)) {
                    item.status = 'error'
                    item.errorMessage = error.response?.data?.message || '上傳失敗，請重試。'
                }
            } finally { uploadControllers.delete(item.id) }
        }
    } finally { uploadQueueRunning = false }
}
const retryImage = item => {
    if (item.status !== 'error' || !item.file) return
    item.progress = 0
    item.status = 'waiting'
    startUploadQueue()
}

// 媒體櫃相關函數
const loadMediaLibrary = async () => {
    loadingMediaLibrary.value = true
    mediaLibraryError.value = ''
    try {
        const res = await axios.get('/api/media/unused', { params: { per_page: 100 } })
        mediaLibraryImages.value = res.data.data || []
    } catch (error) {
        mediaLibraryError.value = '媒體櫃載入失敗，請重試。'
    } finally {
        loadingMediaLibrary.value = false
    }
}

const toggleMediaImage = (image) => {
    if (uploadList.value.some(entry => imageUuidKey(entry.uuid) === imageUuidKey(image.uuid))) return
    const index = selectedMediaImages.value.indexOf(image.uuid)
    if (index > -1) {
        selectedMediaImages.value.splice(index, 1)
    } else {
        if (uploadList.value.length + selectedMediaImages.value.length < 9) {
            selectedMediaImages.value.push(image.uuid)
        } else {
            alert('最多只能選擇 9 張圖片')
        }
    }
}

const addSelectedMediaImages = () => {
    const additions = getNewItemMedia(mediaLibraryImages.value, uploadList.value, selectedMediaImages.value)
    if (uploadList.value.length + additions.length > 9) {
        alert('最多只能選擇 9 張圖片')
        return
    }
    
    if (additions.length < selectedMediaImages.value.length) imageFeedback.value = '已略過已加入的圖片。'
    additions.forEach(image => {
        if (image) {
            const id = uploadId++
            uploadList.value.push({
                id,
                file: null,
                preview: image.preview_url,
                progress: 100,
                status: 'done',
                url: image.preview_url,
                thumb_url: image.thumb_url,
                preview_url: image.preview_url,
                uuid: image.uuid,
                statusFromApi: 'new'
            })
        }
    })
    
    selectedMediaImages.value = []
    showMediaLibrary.value = false
}

// 監聽媒體櫃 modal 打開
watch(showMediaLibrary, (newVal) => {
    if (newVal) loadMediaLibrary()
})

const removeImage = (index) => {
    const item = uploadList.value[index]
    uploadControllers.get(item.id)?.abort()
    if (item.preview?.startsWith('blob:')) URL.revokeObjectURL(item.preview)
    uploadList.value.splice(index, 1)
}

const getImagesForApi = () => getUniqueItemImagePayload(uploadList.value)

const selectedProduct = ref(null)
const products = ref([])

// Debounce 工具函數
const debounce = (func, delay) => {
    let timeoutId
    return (...args) => {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(() => func.apply(null, args), delay)
    }
}

// 實際的搜尋函數
const _searchProduct = async (query) => {
    if (!query || query.trim() === '') {
        products.value = []
        return
    }
    
    try {
        const res = await axios.get('/api/products', { params: { q: query } })
        products.value = res.data.data

        if (!products.value.find(p => p.name === query)) {
            products.value.unshift({
                id: '__create__',
                name: `點選建立新產品：「${query}」`,
                _rawName: query,
                isNew: true,
            })
        }
    } catch (e) {
        console.error('搜尋產品失敗', e)
    }
}

// 使用 debounce 包裝的搜尋函數（500ms 延遲）
const searchProduct = debounce(_searchProduct, 500)


const creatingProduct = ref(false)
const isCreatingProduct = ref(false)
const newProduct = ref({
    name: '',
    brand: '',
    category: null,
    model: '',
    spec: '',
    barcode: ''
})

const onProductSelect = (option) => {
    if (option.isNew) {
        creatingProduct.value = true
        newProduct.value.name = option._rawName
    } else {
        selectedProduct.value = option
        creatingProduct.value = false
    }
}

const confirmCreateProduct = async () => {
    if (isCreatingProduct.value) return
    isCreatingProduct.value = true
    try {
        const res = await axios.post('/api/products', {
            name: newProduct.value.name,
            brand: newProduct.value.brand,
            category_id: newProduct.value.category?.id || null,
            model: newProduct.value.model,
            spec: newProduct.value.spec,
            barcode: newProduct.value.barcode,
        })

        selectedProduct.value = res.data.data
        creatingProduct.value = false
        newProduct.value = {
            name: '',
            brand: '',
            category: null,
            model: '',
            spec: '',
            barcode: ''
        }
        
        // 重新搜尋產品列表以更新
        await searchProduct(selectedProduct.value.name)
    } catch (e) {
        console.error('建立產品失敗', e)
        if (e.response?.data?.message) {
            alert(`建立產品失敗：${e.response.data.message}`)
        } else {
            alert('建立產品失敗，請確認欄位是否正確')
        }
    } finally {
        isCreatingProduct.value = false
    }
}


const cancelCreateProduct = () => {
    if (isCreatingProduct.value) return
    if (showScanner.value) stopScanner()
    creatingProduct.value = false
    selectedProduct.value = null
}


const form = ref({
    name: '',
    description: '',
    location: '',
    quantity: 1,
    price: '',
    purchased_at: getTodayDate(),
    received_at: '',
    used_at: '',
    discarded_at: '',
    expiration_date: '',
    barcode: '',
})

// 表單驗證錯誤訊息
const { receivedWithPurchase, resetArrivalLink } = usePurchaseArrivalLink(form)
const purchaseDateMax = computed(() => getDateMax(receivedWithPurchase.value ? { ...form.value, received_at: '' } : form.value, 'purchased_at'))
const formErrors = ref({})
const itemForm = ref(null)
const confirmedQuantity = ref('')
const dateDrafts = ref({})
const setDateDraft = (field, value) => {
    if (value == null) delete dateDrafts.value[field]
    else dateDrafts.value[field] = value
}
const configuredMaxQuantity = Number(document.querySelector('meta[name="item-max-quantity"]')?.content)
const maxQuantity = Number.isSafeInteger(configuredMaxQuantity) && configuredMaxQuantity > 0 ? configuredMaxQuantity : 100
const { priceMode, calculationBasis, totalAmount, creationSummary, displayedTotal, switchPriceMode, setPriceAmount, resetPricing } = useItemPricing(form, maxQuantity)
const bulkQuantityConfirmed = computed(() => creationSummary.value.valid && isQuantityConfirmed(creationSummary.value.quantity, confirmedQuantity.value))
const quantityLimitExceeded = ref(false)
const quantityInput = createNumericInputHandlers(value => { form.value.quantity = value }, value => {
    const exceedsLimit = /^\d+$/.test(value) && Number(value) > maxQuantity
    quantityLimitExceeded.value = exceedsLimit
    return exceedsLimit ? String(maxQuantity) : value
})
const amountInput = createNumericInputHandlers(setPriceAmount)
const confirmationInput = createNumericInputHandlers(value => { confirmedQuantity.value = value })
const saveBlockReason = computed(() => {
    const dateFields = ['purchased_at', 'received_at', 'used_at', 'discarded_at', 'expiration_date']
    const dateError = dateFields.map(field => getDateTextError(
        form.value[field],
        field === 'expiration_date' ? undefined : getDateMin(form.value, field),
        field === 'expiration_date' ? '9999-12-31' : field === 'purchased_at' ? purchaseDateMax.value : getDateMax(form.value, field),
    )).find(Boolean)
    return getItemSaveBlockReason({
        name: form.value.name, purchasedAt: form.value.purchased_at,
        summary: creationSummary.value, confirmation: confirmedQuantity.value,
        images: uploadList.value, hasDateDrafts: dateFields.some(field => dateDrafts.value[field] != null),
        dateError, productPending: creatingProduct.value || Boolean(selectedProduct.value?.isNew),
    })
})
const saveDisabled = computed(() => isSubmitting.value || Boolean(saveBlockReason.value))
const formatAmount = value => value == null ? '未填寫' : value.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
watch(() => [form.value.quantity, form.value.price, priceMode.value, totalAmount.value], () => { confirmedQuantity.value = '' }, { flush: 'sync' })
// 實際的分類搜尋函數
const _searchCategory = async (query) => {
    searchQuery.value = query
    // 這邊呼叫 GET API 搜尋
    try {
        const res = await axios.get('/api/categories', { params: { q: query } })
        categories.value = Array.isArray(res.data?.data) ? res.data.data : []

        // 如果沒有完全相符的分類，加入「新增分類」選項
        if (query && !categories.value.find(c => c.name === query)) {
            categories.value.unshift({
                id: '__create__',
                name: `點選以建立新分類：「${query}」`,
                _rawName: query,
                isNew: true
            })
        }
    } catch (err) {
        console.error('搜尋分類失敗', err)
    }
}

// 使用 debounce 包裝的分類搜尋函數（500ms 延遲）
const onSearch = debounce(_searchCategory, 500)

const onSelect = async (option) => {
    if (option && option.isNew) {
        // 顯示確認對話框
        const categoryName = option._rawName || option.name.replace('點選以建立新分類：「', '').replace('」', '')
        const confirmed = confirm(`是否要新增分類「${categoryName}」？`)
        
        if (confirmed) {
            await createCategory(categoryName)
        } else {
            // 取消選擇，回到未選擇狀態
            newProduct.value.category = null
        }
    } else if (option) {
        newProduct.value.category = option
    }
}

const createCategory = async (categoryName) => {
    if (!categoryName || !categoryName.trim()) {
        alert('請輸入分類名稱')
        return
    }
    
    if (creatingCategory.value) return
    creatingCategory.value = true
    
    try {
        const res = await axios.post('/api/categories', { name: categoryName.trim() })
        const newCategory = res.data.items[0]
        
        // 添加到分類列表
        if (!categories.value.find(c => c.id === newCategory.id)) {
            categories.value.push(newCategory)
        }
        
        // 自動選中新建的分類
        newProduct.value.category = newCategory
    } catch (e) {
        console.error('新增分類失敗', e)
        if (e.response?.data?.message) {
            alert(`新增分類失敗：${e.response.data.message}`)
        } else {
            alert('新增分類失敗，請確認分類名稱是否正確')
        }
        // 失敗時清空選擇
        newProduct.value.category = null
    } finally {
        creatingCategory.value = false
    }
}

onMounted(async () => {
    window.addEventListener('dragenter', handleWindowDragEnter)
    window.addEventListener('dragover', handleWindowDragOver)
    window.addEventListener('dragleave', handleWindowDragLeave)
    window.addEventListener('drop', handleWindowDrop)

    try {
        await axios.get('/api/user') // Laravel Sanctum 預設是這個
        // 如果成功，就不做事
    } catch (error) {
        if (error.response?.status === 401) {
            router.push('/login') // 或使用名稱：{ name: 'Login' }
            return
        }
    }


    try {
        const res = await axios.get('/api/categories')
        categories.value = Array.isArray(res.data?.data) ? res.data.data : []
    } catch (error) {
        console.error('讀取分類失敗', error)
    }
})

onBeforeUnmount(() => {
    pageActive = false
    uploadControllers.forEach(controller => controller.abort())
    uploadList.value.forEach(image => { if (image.preview?.startsWith('blob:')) URL.revokeObjectURL(image.preview) })
    leaveGuard.dispose()
    if (html5QrCode) stopScanner()
    window.removeEventListener('dragenter', handleWindowDragEnter)
    window.removeEventListener('dragover', handleWindowDragOver)
    window.removeEventListener('dragleave', handleWindowDragLeave)
    window.removeEventListener('drop', handleWindowDrop)
})

const uploadImage = async (e) => {
    const file = e.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('image', file)

    try {
        const res = await axios.post('/api/item-images', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        uploadList.value.push({
            id: null,
            file,
            preview: URL.createObjectURL(file),
            progress: 0,
            status: 'done',
            url: res.data.url
        })
    } catch (error) {
        console.error('上傳失敗', error.response?.data ?? error)
        alert('上傳失敗，請檢查檔案格式或大小')
    }
}

const blurNumberOnWheel = event => {
    const input = event.target
    if (input.tagName === 'INPUT' && input.type === 'number' && document.activeElement === input) input.blur()
}

const submitForm = async (stay = false) => {
    if (saveDisabled.value) return
    formErrors.value = {}
    if (!itemForm.value?.reportValidity()) return
    const summary = creationSummary.value
    if (!summary.valid) {
        formErrors.value[summary.field] = summary.message
        await nextTick()
        document.getElementById(`create-${summary.field}`)?.focus()
        return
    }
    if (!isQuantityConfirmed(summary.quantity, confirmedQuantity.value)) {
        formErrors.value.confirmation = '請重新輸入本次建立件數，再按儲存。'
        await nextTick()
        document.getElementById('confirm-quantity')?.focus()
        return
    }
    if (creatingProduct.value || selectedProduct.value?.isNew) {
        alert('請先完成或取消建立產品。')
        return
    }
    if (uploadList.value.some(image => image.status !== 'done')) {
        alert('請等待照片上傳完成；上傳失敗的照片請重試或移除。')
        return
    }
    isSubmitting.value = true

    const images = getImagesForApi()

    const payload = {
        ...form.value,
        quantity: summary.quantity,
        price: summary.unitPrice,
        images,
        category_id: selectedCategory.value?.id ?? null,
        product_id: selectedProduct.value?.id ?? null,
        source_product_id: selectedProduct.value?.is_bundle ? selectedProduct.value.id : null,
    }

    try {
        await axios.post('/api/items', payload)
        if (!pageActive) return
        dateDrafts.value = {}
        leaveGuard.markSaved()

        await Swal.fire({
            icon: 'success',
            title: '成功',
            text: `已新增 ${summary.quantity} 筆物品`,
            confirmButtonText: '確定'
        })

        if (stay) {
            resetForm()
        } else {
            router.push('/items')
        }
    } catch (error) {
        if (!pageActive) return
        console.error('儲存失敗', error.response?.data ?? error)

        // 處理 422 表單驗證錯誤
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const errors = error.response.data.errors
            
            // 將錯誤訊息對應到表單欄位
            Object.keys(errors).forEach(field => {
                const errorMessages = errors[field]
                formErrors.value[field] = Array.isArray(errorMessages) ? errorMessages[0] : errorMessages
            })

            // 顯示 SweetAlert 提示
            await Swal.fire({
                icon: 'error',
                title: '表單驗證失敗',
                text: '請檢查表單欄位並修正錯誤',
                confirmButtonText: '確定'
            })
        } else {
            // 處理其他錯誤
            let errorMessage = '儲存失敗，請確認欄位填寫正確'
            if (error.response?.data?.message) {
                errorMessage = error.response.data.message
            }

            await Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: errorMessage,
                confirmButtonText: '確定'
            })
        }
    } finally {
        isSubmitting.value = false
    }
}


const resetForm = () => {
    resetArrivalLink()
    dateDrafts.value = {}
    form.value = {
        name: '',
        description: '',
        location: '',
        quantity: 1,
        price: '',
        purchased_at: getTodayDate(),
        received_at: '',
        used_at: '',
        discarded_at: '',
        expiration_date: '',
        barcode: '',
    }
    formErrors.value = {}
    resetPricing()
    confirmedQuantity.value = ''
    selectedCategory.value = null
    selectedProduct.value = null
    creatingProduct.value = false
    uploadList.value.forEach(image => { if (image.preview?.startsWith('blob:')) URL.revokeObjectURL(image.preview) })
    uploadList.value = []
    imageFeedback.value = ''
    quantityLimitExceeded.value = false
    selectedMediaImages.value = []
    newProduct.value = { name: '', brand: '', category: null, model: '', spec: '', barcode: '' }
    manufactureDate.value = ''
    expirationValue.value = 0
    expirationUnit.value = 'years'
    leaveGuard.markSaved()
}

let html5QrCode

const startBarcodeScan = async (target) => {
    scanTarget.value = target
    barcodeDetected.value = false
    showScanner.value = true
    await nextTick()
    html5QrCode = new Html5Qrcode("scanner")

    try {
        // 計算橫向掃描框尺寸（適合手機直立使用）
        // 使用視窗寬度的 80%，高度為寬度的 40%（2.5:1 的比例）
        const viewportWidth = window.innerWidth
        const viewportHeight = window.innerHeight
        const scanBoxWidth = Math.min(viewportWidth * 0.8, 500)
        const scanBoxHeight = scanBoxWidth * 0.4 // 2.5:1 的比例

        await html5QrCode.start(
            { 
                facingMode: "environment"
            },
            { 
                fps: 10, 
                qrbox: { width: scanBoxWidth, height: scanBoxHeight },
                aspectRatio: 1.0,
                disableFlip: false
            },
            async (decodedText, result) => {
                // 檢測到條碼時顯示視覺反饋
                barcodeDetected.value = true
                
                // 短暫延遲後停止掃描並顯示確認
                setTimeout(async () => {
                    await stopScanner()
                    
                    // 顯示確認對話框
                    const confirmed = confirm(`掃描到的條碼：${decodedText}\n\n是否要使用這個條碼？`)
                    
                if (confirmed) {
                    // 根據目標填入對應的輸入框
                    if (target === 'productBarcode') {
                        newProduct.value.barcode = decodedText
                    }
                }
                }, 500) // 給用戶 0.5 秒看到綠色反饋
            },
            (errorMessage) => {
                // 掃描錯誤時的處理（不顯示錯誤訊息避免干擾）
                barcodeDetected.value = false
            }
        )
    } catch (err) {
        alert("無法啟動相機掃描，請確認瀏覽器權限")
        console.error(err)
        showScanner.value = false
        scanTarget.value = null
        barcodeDetected.value = false
    }
}

const stopScanner = async () => {
    if (html5QrCode) {
        try {
            await html5QrCode.stop()
            html5QrCode.clear()
        } catch (err) {
            console.error('停止掃描器時出錯', err)
        }
        html5QrCode = null
    }
    showScanner.value = false
    scanTarget.value = null
    barcodeDetected.value = false
}

const showManufactureDateModal = ref(false)
const manufactureDate = ref('')
const expirationValue = ref(0)
const expirationUnit = ref('years')

const calculatedExpirationDate = computed(() => {
    if (!manufactureDate.value || !expirationValue.value) return ''

    const date = new Date(manufactureDate.value)

    switch (expirationUnit.value) {
        case 'years':
            date.setFullYear(date.getFullYear() + expirationValue.value)
            break
        case 'months':
            date.setMonth(date.getMonth() + expirationValue.value)
            break
        case 'days':
            date.setDate(date.getDate() + expirationValue.value)
            break
    }

    return date.toISOString().split('T')[0]
})

const applyCalculatedDate = () => {
    if (calculatedExpirationDate.value) {
        form.value.expiration_date = calculatedExpirationDate.value
        showManufactureDateModal.value = false
        // 重置輸入值
        manufactureDate.value = ''
        expirationValue.value = 0
        expirationUnit.value = 'years'
    }
}

const takeDraftSnapshot = () => {
    const pendingDates = Object.fromEntries(Object.entries(dateDrafts.value).filter(([key, value]) => {
        const current = key === 'manufactureDate' ? manufactureDate.value : form.value[key]
        const parsed = parseDateText(value)
        return (parsed ? formatDateText(parsed) : value) !== (current || '')
    }).sort(([left], [right]) => left.localeCompare(right)))
    return {
        form: { ...form.value },
        priceMode: priceMode.value,
        calculationBasis: calculationBasis.value,
        totalAmount: totalAmount.value,
        confirmedQuantity: confirmedQuantity.value,
        receivedWithPurchase: receivedWithPurchase.value,
        images: uploadList.value.map(image => image.uuid || `pending:${image.id}`),
        selectedProduct: selectedProduct.value?.id ?? null,
        selectedCategory: selectedCategory.value?.id ?? null,
        newProduct: { ...newProduct.value, category: newProduct.value.category?.id ?? null },
        selectedMedia: [...selectedMediaImages.value],
        manufactureDate: manufactureDate.value,
        expirationValue: expirationValue.value,
        expirationUnit: expirationUnit.value,
        pendingDates,
    }
}
const leaveGuard = createUnsavedChangesGuard({ snapshot: takeDraftSnapshot, pending: () => isSubmitting.value })
onBeforeRouteLeave(() => leaveGuard.confirmLeave())

</script>

<style scoped>
.photo-tile .photo-preview-trigger { display: block; width: 100%; height: 100%; min-height: 0; padding: 0; border: 0; border-radius: inherit; background: transparent; overflow: hidden; -webkit-touch-callout: none; user-select: none; -webkit-user-select: none; }
.photo-full-preview { display: block; width: 100%; max-height: 75dvh; object-fit: contain; }
.photo-preview { -webkit-touch-callout: none; user-select: none; -webkit-user-select: none; }
/* 將 HDR 預覽限制為標準亮度，避免照片與一般介面的亮度落差。 */
.photo-preview, .photo-full-preview { dynamic-range-limit: standard; }
.photo-tile { -webkit-touch-callout: none; user-select: none; -webkit-user-select: none; }
.photo-sortable-chosen { z-index: 20; }
.photo-sortable-chosen .photo-preview-trigger { transform: scale(1.04); opacity: 0.75; box-shadow: var(--shadow-toast); }
.photo-sortable-ghost { opacity: 0.25; background: var(--brand-soft); }
.photo-sortable-ghost .photo-preview-trigger { transform: none; box-shadow: none; }
.photo-sortable-fallback { opacity: 0.8 !important; z-index: 10000 !important; }
.photo-sortable-fallback .photo-preview-trigger { transform: scale(1.05); box-shadow: var(--shadow-dialog); }
.photo-sortable-chosen .photo-remove, .photo-sortable-fallback .photo-remove { visibility: hidden; }
@media (prefers-reduced-motion: reduce) {
    .photo-sortable-chosen .photo-preview-trigger, .photo-sortable-fallback .photo-preview-trigger { transform: none; }
}

.create-page { min-height: 100vh; padding: 24px 16px 32px; background: var(--canvas); color: var(--text); }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
h1 { margin: 0; font-size: 24px; line-height: 1.3; font-weight: 600; }
h2, h3 { margin: 0; font-size: 18px; line-height: 1.5; font-weight: 600; }
.back-link { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 14px; text-decoration: none; flex-shrink: 0; }
.item-form { max-width: 720px; margin-inline: auto; display: grid; align-items: start; gap: 24px; margin-top: 24px; }
.form-section { min-width: 0; padding: 16px; display: grid; gap: 24px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
.form-section > div { min-width: 0; }
.create-page label { font-size: 14px; font-weight: 500; line-height: 1.5; }
.create-page label { margin-bottom: 8px; }
.create-page input:not([type=file]):not([type=radio]):not([type=checkbox]), .create-page textarea, .create-page select { width: 100%; min-width: 0; min-height: 44px; padding: 10px var(--control-input-padding, 12px); border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 16px; font-weight: 400; line-height: 1.5; }
.create-page textarea { resize: vertical; }
.create-page input[aria-invalid=true], .create-page textarea[aria-invalid=true] { border-color: var(--danger); }
.create-page input::placeholder, .create-page textarea::placeholder { color: var(--text-muted); opacity: 1; }
.create-page p { line-height: 1.5; }
.create-page button { min-height: 44px; border-radius: var(--radius-control); cursor: pointer; font-size: 14px; font-weight: 500; }
.create-page button:disabled { cursor: not-allowed; }
a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.photo-heading, .expiry-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; }
.photo-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
@media (min-width: 480px) {
    .photo-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}
.photo-count { font-size: 14px; font-weight: 400; color: var(--text-muted); }
.photo-remove { position: absolute; top: 0; right: 0; transform: translate(50%, -50%); z-index: 10; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; background: transparent; color: var(--text); border: 0; }
.photo-remove-badge { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--surface); border-radius: 50%; background: var(--border-control); color: var(--on-brand); }
.photo-remove:hover .photo-remove-badge { background: var(--text-muted); }
.photo-preview--pending { opacity: 0.7; }
.photo-placeholder { width: 100%; height: 100%; border-radius: inherit; background: var(--canvas); }
.photo-state { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0; background: transparent; color: var(--text-muted); font-size: 12px; line-height: 1.5; white-space: nowrap; pointer-events: none; }
.photo-spinner { display: block; width: 20px; height: 20px; border: 2px solid var(--border-subtle); border-top-color: var(--text-muted); border-radius: 50%; animation: photo-spin 0.9s linear infinite; }
@keyframes photo-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .photo-spinner { animation: none; } }
.photo-retry { position: absolute; bottom: 0; left: 0; right: 0; min-height: 44px; padding: 4px; border: 1px solid var(--danger); background: var(--danger-soft); color: var(--danger); font-size: 12px; }
.image-help, .image-feedback { margin: 0 0 8px; font-size: 14px; line-height: 1.5; color: var(--text-muted); }
.image-errors { margin: 12px 0 0; padding-left: 20px; font-size: 14px; color: var(--danger); overflow-wrap: anywhere; }
.image-feedback { color: var(--brand-ink); }
.media-library-error { color: var(--danger); font-size: 14px; }
.dialog-close { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; color: var(--text); border: 1px solid var(--border-control); }
.text-action { padding: 8px 0; color: var(--brand-ink); text-decoration: underline; text-underline-offset: 3px; }
.expiry-heading label { margin-bottom: 0; }
.form-actions { display: flex; flex-wrap: wrap; gap: 12px; }
.product-link-help { margin: 8px 0 0; }
/* 兩套下拉套件共用根 class；此頁只由 __tags 繪製控制項邊框。 */
.create-page :deep(.multiselect) { display: block; min-width: 0; min-height: 44px; margin: 0; padding: 0; border: 0; background: transparent; box-shadow: none; color: var(--text); }
.create-page :deep(.multiselect__tags) { min-height: 44px; padding: 10px 40px 0 12px; border-color: var(--border-control); border-radius: var(--radius-control); background: var(--surface); font-size: 16px; }
.create-page :deep(.multiselect__input), .create-page :deep(.multiselect__single) { min-width: 0; margin-bottom: 10px; padding: 0; color: var(--text); background: var(--surface); font-size: 16px; line-height: 24px; }
.create-page :deep(.multiselect__input) { min-height: 24px; border: 0; border-radius: 0; }
.create-page :deep(.multiselect__placeholder) { color: var(--text-muted); padding: 0; margin-bottom: 10px; font-size: 16px; line-height: 24px; }
.create-page :deep(.multiselect__content-wrapper) { border-color: var(--border-control); background: var(--surface); color: var(--text); }
.create-page :deep(.multiselect__option) { min-height: 44px; white-space: normal; line-height: 1.5; overflow-wrap: anywhere; }
.create-page :deep(.multiselect__option--highlight), .create-page :deep(.multiselect__option--highlight::after), .create-page :deep(.multiselect__tag) { background: var(--brand); color: var(--on-brand); }
.create-page :deep(.multiselect__option--selected::after) { background: var(--brand-soft); color: var(--brand-ink); }
.create-page :deep(.multiselect__select::before) { border-top-color: var(--text-muted); }
.create-page :deep(.multiselect__option--selected) { background: var(--brand-soft); color: var(--brand-ink); }
.create-page :deep(.multiselect__option--selected.multiselect__option--highlight), .create-page :deep(.multiselect__option--selected.multiselect__option--highlight::after) { background: var(--brand-hover); color: var(--on-brand); }
.create-page :deep(.multiselect:focus-within) { outline: 2px solid var(--brand-ink); outline-offset: 2px; border-radius: var(--radius-control); }

@media (min-width: 768px) {
    .create-page { padding: 32px 24px; }
    h1 { font-size: 28px; }
}


.arrival-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.arrival-heading > label { margin-bottom: 0; }
.same-day-toggle { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; cursor: pointer; color: var(--text-muted); }
.same-day-toggle input { width: 20px; height: 20px; accent-color: var(--brand); }

.amount-equation { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
.equation-field { display: grid; min-width: 0; }
.equation-field .quantity-help, .equation-field .quantity-error { margin: 4px 0 0; font-size: 14px; line-height: 1.5; color: var(--text-muted); }
.equation-field .quantity-help--error { color: var(--danger); }
.equation-field .quantity-error { color: var(--danger); }
.equation-field > label:not(.sr-only), .equation-label { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; min-height: 44px; margin-bottom: 8px; }
.create-page .price-choice { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; margin: 0; cursor: pointer; }
.price-choice input { width: 20px; height: 20px; flex-shrink: 0; accent-color: var(--brand); }
.input-state { justify-self: start; padding: 2px 4px; border-radius: 4px; font-size: 12px; line-height: 1.5; font-weight: 400; background: var(--disabled); color: var(--text-muted); }
.equation-symbol { display: none; align-items: center; justify-content: center; min-height: 44px; font-size: 16px; color: var(--text-muted); }
.create-page .amount-equation .equation-field input:disabled { background: var(--disabled); color: var(--text-muted); border-color: var(--border-subtle); }
.create-page .amount-equation .equation-field input:not([type=radio]):not(:disabled) { border-color: var(--brand-ink); }
.create-page .amount-equation .equation-field input[aria-invalid=true] { border-color: var(--danger); }
.amount-equation input[type=number] { appearance: textfield; -moz-appearance: textfield; }
.amount-equation input::-webkit-inner-spin-button, .amount-equation input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.equation-help p { margin: 0; font-size: 14px; line-height: 1.5; color: var(--text-muted); }
.equation-help .rounding-note { margin-top: 4px; color: var(--warning); }
.equation-help .equation-error { margin-top: 4px; color: var(--danger); }
@media (min-width: 768px) {
    .amount-equation { gap: 8px; grid-template-columns: minmax(0, 0.75fr) 20px minmax(0, 1fr) 20px minmax(0, 1.15fr); }
    .equation-symbol { display: flex; margin-top: 52px; }
}

.creation-summary { padding: 16px; border: 0; border-radius: var(--radius-control); background: var(--canvas); }
.creation-summary p { margin: 0 0 12px; font-size: 14px; color: var(--text-muted); }
.creation-summary p:last-child { margin-bottom: 0; }
.creation-summary .creation-count { color: var(--text); font-size: 18px; font-weight: 500; }
.creation-count strong { color: var(--brand-ink); font-size: 24px; font-weight: 600; }
.creation-summary dl { display: grid; gap: 12px; margin: 0 0 16px; font-size: 16px; }
.creation-summary dl > div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }
.creation-summary dt { color: var(--text-muted); }
.creation-summary dd { margin: 0; overflow-wrap: anywhere; }
.creation-summary .rounding-note { color: var(--warning); }
.creation-summary .summary-error { color: var(--danger); }
.quantity-confirmation { padding: 16px; border: 0; border-radius: var(--radius-control); background: var(--warning-soft); }
.quantity-confirmation--confirmed { background: var(--canvas); }
.quantity-confirmation label { display: block; }
.quantity-confirmation p { min-height: 1.5em; margin: 8px 0 0; font-size: 14px; line-height: 1.5; color: var(--warning); }
.quantity-confirmation--confirmed p { color: var(--text-muted); }
.supplementary-content { display: grid; gap: 24px; min-width: 0; }
.form-fields { display: grid; gap: 24px; min-width: 0; }
.summary-panel { min-width: 0; gap: 16px; }
.summary-item { display: flex; align-items: center; gap: 12px; min-width: 0; }
.summary-thumbnail { display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; flex-shrink: 0; border-radius: var(--radius-control); background: var(--canvas); color: var(--text-muted); overflow: hidden; }
.summary-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.summary-name { min-width: 0; margin: 0; font-size: 16px; font-weight: 500; overflow-wrap: anywhere; }
.summary-dates { display: grid; gap: 12px; margin: 0; font-size: 14px; line-height: 1.5; }
.summary-dates > div { display: grid; grid-template-columns: 76px minmax(0, 1fr); gap: 8px; }
.summary-dates dt { color: var(--text-muted); }
.summary-dates dd { margin: 0; overflow-wrap: anywhere; }
.summary-panel .form-actions { display: grid; grid-template-columns: minmax(0, 1fr); }
@media (min-width: 1024px) {
    .item-form { max-width: none; grid-template-columns: minmax(0, 1fr) 320px; }
    .summary-panel { position: sticky; top: 88px; max-height: calc(100dvh - 104px); overflow-y: auto; scrollbar-gutter: stable; }
}

.scanner-container #scanner {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
}

.scanner-container #scanner video,
.scanner-container #scanner canvas {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    object-fit: cover !important;
}
</style>
