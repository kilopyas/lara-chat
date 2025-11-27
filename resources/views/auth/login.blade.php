@extends('partials.layout.layout')

@section('title', 'Login')

@push('head')
<style>
    .auth-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        width: 100%;
        max-width: 420px;
    }
    h1 { margin: 0 0 12px; font-size: 20px; }
    label { display: block; font-size: 13px; margin-bottom: 4px; }
    input[type="text"], input[type="email"], input[type="password"] {
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--panel);
        color: var(--text);
        margin-bottom: 12px;
    }
    button {
        width: 100%;
        padding: 10px;
        border: none;
        border-radius: 8px;
        background: var(--primary);
        color: #022c22;
        font-weight: 700;
        cursor: pointer;
    }
    .errors {
        background: #422121;
        border: 1px solid #7f1d1d;
        color: #fecdd3;
        padding: 8px 10px;
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 13px;
    }
</style>
@endpush

@section('content')
<div class="auth-wrap">
    <div class="card">
        <h1>Log in</h1>
        <p style="opacity:.8; font-size:13px; margin:0 0 12px;">Use your account to join rooms and keep your identity consistent.</p>

        @if($errors->any())
            <div class="errors">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <button type="submit">Continue</button>
        </form>
    </div>
</div>
@endsection
