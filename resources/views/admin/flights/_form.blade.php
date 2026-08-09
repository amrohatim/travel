@php
    $selectedOfficeId = old('office_id', $flight->office_id ?? request('office_id'));
    $selectedFrom = old('from', $flight->from ?? '');
    $selectedTo = old('to', $flight->to ?? '');
    $selectedDepartureTime = old(
        'departure_time',
        isset($flight) && $flight->departure_time
            ? \Carbon\Carbon::parse($flight->departure_time)->format('Y-m-d\TH:i')
            : ''
    );
    $selectedPrice = old('price', $flight->price ?? '');
    $selectedSeats = old('seats', $flight->seats ?? '');
    $hasDiscount = old('has_discount', isset($flight) ? (int) $flight->has_discount : 0);
    $selectedDiscountPercentage = old('discount_percentage', $flight->discount_percentage ?? '');
    $currentFinalPrice = isset($flight)
        ? $flight->normalizedDiscount()['final_price']
        : $selectedPrice;
@endphp

<div class="col-md-6">
    <label class="form-label">Office</label>
    <select name="office_id" class="form-select" required @disabled($hasBookings ?? false)>
        <option value="">Select office</option>
        @foreach ($offices as $office)
            <option value="{{ $office->id }}" @selected((string) $selectedOfficeId === (string) $office->id)>{{ $office->name }}</option>
        @endforeach
    </select>
    @if (($hasBookings ?? false) && isset($flight))
        <input type="hidden" name="office_id" value="{{ $flight->office_id }}">
    @endif
</div>

<div class="col-md-6">
    <label class="form-label">Departure Time</label>
    <input type="datetime-local" name="departure_time" class="form-control" value="{{ $selectedDepartureTime }}" required @readonly($hasBookings ?? false)>
</div>

<div class="col-md-6">
    <label class="form-label">From</label>
    <select name="from" class="form-select" required @disabled($hasBookings ?? false)>
        <option value="">Select departure state</option>
        @foreach ($states as $state)
            <option value="{{ $state->name }}" @selected($selectedFrom === $state->name)>{{ $state->name }}</option>
        @endforeach
    </select>
    @if (($hasBookings ?? false) && isset($flight))
        <input type="hidden" name="from" value="{{ $flight->from }}">
    @endif
</div>

<div class="col-md-6">
    <label class="form-label">To</label>
    <select name="to" class="form-select" required @disabled($hasBookings ?? false)>
        <option value="">Select destination state</option>
        @foreach ($states as $state)
            <option value="{{ $state->name }}" @selected($selectedTo === $state->name)>{{ $state->name }}</option>
        @endforeach
    </select>
    @if (($hasBookings ?? false) && isset($flight))
        <input type="hidden" name="to" value="{{ $flight->to }}">
    @endif
</div>

<div class="col-md-4">
    <label class="form-label">Price</label>
    <input type="number" name="price" class="form-control" min="0" value="{{ $selectedPrice }}" required>
</div>

<div class="col-md-4">
    <label class="form-label">Seats</label>
    <input type="number" name="seats" class="form-control" min="1" value="{{ $selectedSeats }}" required @readonly($hasBookings ?? false)>
</div>

<div class="col-md-4 d-flex align-items-end">
    <div class="form-check">
        <input type="checkbox" name="has_discount" id="has_discount" value="1" class="form-check-input" @checked((bool) $hasDiscount)>
        <label class="form-check-label" for="has_discount">Apply discount</label>
    </div>
</div>

<div class="col-md-6" id="discount-percentage-field">
    <label class="form-label">Discount Percentage</label>
    <input type="number" name="discount_percentage" class="form-control" min="0" max="100" value="{{ $selectedDiscountPercentage }}">
</div>

<div class="col-md-6" id="final-price-field">
    <label class="form-label">Price After Discount</label>
    <input
        type="text"
        id="final_price_preview"
        class="form-control"
        value="{{ $currentFinalPrice }}"
        readonly
    >
</div>

<script>
    (() => {
        const checkbox = document.getElementById('has_discount');
        const discountField = document.getElementById('discount-percentage-field');
        const discountInput = discountField?.querySelector('input[name="discount_percentage"]');
        const priceInput = document.querySelector('input[name="price"]');
        const finalPriceField = document.getElementById('final-price-field');
        const finalPricePreview = document.getElementById('final_price_preview');

        if (!checkbox || !discountField || !discountInput || !priceInput || !finalPriceField || !finalPricePreview) {
            return;
        }

        const updateFinalPrice = () => {
            const price = Number.parseInt(priceInput.value, 10);
            const percentage = Number.parseInt(discountInput.value, 10);
            const hasValidPrice = Number.isFinite(price) && price >= 0;
            const hasValidPercentage = Number.isFinite(percentage) && percentage > 0;

            if (!hasValidPrice) {
                finalPricePreview.value = '';
                return;
            }

            if (!checkbox.checked || !hasValidPercentage) {
                finalPricePreview.value = String(price);
                return;
            }

            const discountValue = Math.floor((price * percentage) / 100);
            const finalPrice = Math.max(0, price - discountValue);
            finalPricePreview.value = String(finalPrice);
        };

        const syncDiscountField = () => {
            const enabled = checkbox.checked;
            discountField.style.display = enabled ? '' : 'none';
            discountInput.required = enabled;
            finalPriceField.style.display = '';

            if (!enabled) {
                discountInput.value = '';
            }

            updateFinalPrice();
        };

        checkbox.addEventListener('change', syncDiscountField);
        discountInput.addEventListener('input', updateFinalPrice);
        priceInput.addEventListener('input', updateFinalPrice);
        syncDiscountField();
    })();
</script>
