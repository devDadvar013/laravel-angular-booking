<div style="font-family: Tahoma, sans-serif; direction: rtl; text-align: right;">
    <h2>رزرو شما با موفقیت ثبت شد</h2>
    <p>سلام {{ $booking->customer_name }} عزیز،</p>
    <p>رزرو شما با جزئیات زیر ثبت گردید:</p>
    <ul>
        <li>کد رزرو: {{ $booking->id }}</li>
        <li>شروع: {{ $booking->start_time->format('Y-m-d H:i') }}</li>
        <li>پایان: {{ $booking->end_time->format('Y-m-d H:i') }}</li>
        <li>وضعیت: {{ $booking->status->value }}</li>
    </ul>
    <p>با تشکر از اعتماد شما.</p>
</div>
