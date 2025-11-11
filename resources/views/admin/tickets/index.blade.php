@extends('layouts.app')

@section('title', 'مدیریت تیکت‌ها')

@section('content')
<div class="container">
    <h2 style="margin-bottom:15px;">فهرست تیکت‌ها</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-striped" style="direction:rtl;">
            <thead>
                <tr>
                    <th>کاربر</th>
                    <th>عنوان</th>
                    <th>توضیحات</th>
                    <th>وضعیت</th>
                    <th>پاسخ</th>
                    <th>اقدامات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $t)
                    <tr>
                        <td>{{ $t->user ? ($t->user->name ?? $t->user->email) : '-' }}</td>
                        <td>{{ $t->title }}</td>
                        <td>{{ $t->description }}</td>
                        <td>{{ $t->status === 'closed' ? 'بسته' : 'باز' }}</td>
                        <td>{{ $t->reply ?? '-' }}</td>
                        <td>
                            @if($t->status !== 'closed')
                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    <form method="POST" action="{{ route('admin.tickets.reply', ['ticket' => $t->id]) }}" style="display:inline-flex; gap:8px; align-items:center;">
                                        @csrf
                                        <input type="text" name="reply" class="form-control" placeholder="پاسخ ادمین" required style="max-width:300px;">
                                        <button type="submit" class="btn btn-primary" style="padding:6px 12px;">ارسال پاسخ</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.tickets.close', ['ticket' => $t->id]) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary" style="padding:6px 12px;">بستن تیکت</button>
                                    </form>
                                </div>
                            @else
                                <span style="color:#6c757d;">این تیکت بسته شده است</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:10px;">
        {{ $tickets->links() }}
    </div>
</div>
@endsection