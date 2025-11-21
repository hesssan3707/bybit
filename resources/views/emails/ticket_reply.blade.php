<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8" />
    <title>پاسخ به تیکت</title>
</head>
<body style="font-family:Tahoma, Arial, sans-serif; direction:rtl; text-align:right;">
    <p>کاربر گرامی،</p>
    <p>پاسخ جدیدی برای تیکت شما ثبت شد.</p>

    <p><strong>عنوان تیکت:</strong> {{ $ticket->title }}</p>
    <p><strong>متن پاسخ ادمین:</strong></p>
    <blockquote style="background:#f8f9fa; border-right:3px solid #007bff; padding:10px;">{{ $ticket->reply }}</blockquote>

    <p>ادمین پاسخ‌دهنده: {{ $admin->name ?? $admin->email }}</p>

    <p style="margin-top:15px;">از این پس ادامه‌ی گفتگو در ایمیل انجام خواهد شد. لطفاً همین ایمیل را پاسخ دهید تا پیام شما به تیم پشتیبانی برسد.</p>

    <p style="color:#6c757d;">با تشکر از همکاری شما</p>
</body>
</html>