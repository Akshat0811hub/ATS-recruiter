@extends('layouts.app')
@section('title', 'Roles & Granular Permissions')
@section('page_title', 'Role-Based Access Control (RBAC) & Permissions Matrix')
@section('page_subtitle', 'Server-side enforced permissions for Super Admin, Manager, Team Leader, Recruiter, and Client HR')

@section('content')
<div class="space-y-6 text-xs">
    @foreach($roles as $role)
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <span class="font-mono text-[#D3003B] font-bold uppercase">{{ $role->slug }}</span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $role->name }}</h2>
                    <p class="text-slate-500">{{ $role->description }}</p>
                </div>
                <span class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 font-semibold">{{ $role->permissions->count() }} Permissions Granted</span>
            </div>

            @if($role->slug !== 'super_admin')
                <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}" class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                        @foreach($permissions as $module => $perms)
                            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-800">
                                <div class="font-bold uppercase text-[10px] text-slate-400 mb-2">{{ $module }}</div>
                                <div class="space-y-1.5">
                                    @foreach($perms as $perm)
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                                   {{ $role->permissions->contains('id', $perm->id) ? 'checked' : '' }}>
                                            <span>{{ $perm->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#0B1528] text-white font-semibold">Save Permissions for {{ $role->name }}</button>
                    </div>
                </form>
            @endif
        </div>
    @endforeach
</div>
@endsection
