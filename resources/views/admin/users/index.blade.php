@extends('layouts.admin')

@section('title', 'Akses Akun')
@section('heading', 'Akses Akun')
@section('subheading', 'Atur modul COGS, Kasir, dan Admin per pengguna')

@section('content')
    <div class="page-toolbar mb-4">
        <p class="text-sm text-slate-500">{{ $users->count() }} akun</p>
        <a href="{{ route('admin.users.create') }}" class="btn-primary btn-sm">+ Akun Baru</a>
    </div>

    <div class="card p-0 overflow-hidden">
        @foreach ($users as $user)
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">
                        {{ $user->name }}
                        @if ($user->isRoot())
                            <span class="badge badge-amber ml-1">Root</span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($user->accessibleModules() as $module)
                            <span class="badge badge-blue">{{ $module->label() }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap justify-end gap-1">
                    @if (! $user->isRoot() || auth()->user()->isRoot())
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn-sm btn-outline">Edit</a>
                        <form
                            action="{{ route('admin.users.reset-password', $user) }}"
                            method="POST"
                            onsubmit="return confirm({{ json_encode('Reset password '.$user->name.' ke password sementara? User wajib ganti password saat login berikutnya.') }})"
                        >
                            @csrf
                            <button type="submit" class="btn-sm btn-outline">Reset Password</button>
                        </form>
                        @if (auth()->id() !== $user->id)
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Hapus akun ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
