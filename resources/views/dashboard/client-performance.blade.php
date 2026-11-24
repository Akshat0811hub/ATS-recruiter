@extends('layouts.app')
@section('title', 'Client Hiring Performance')
@section('page_title', 'Client-Wise Hiring & Conversion Performance')
@section('page_subtitle', 'Track requirements, submissions, interview conversions, offers, and placements per client organisation')

@section('content')
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Client Hiring Performance Matrix</h2>
        <a href="{{ route('clients.index') }}" class="text-xs text-[#1E40AF] hover:underline">Manage Clients →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 uppercase text-[10px]">
                <tr>
                    <th class="px-4 py-3">Client ID</th>
                    <th class="px-4 py-3">Company Name</th>
                    <th class="px-4 py-3">Industry</th>
                    <th class="px-4 py-3 text-right">Requirements</th>
                    <th class="px-4 py-3 text-right">Profiles Submitted</th>
                    <th class="px-4 py-3 text-right">Interviews</th>
                    <th class="px-4 py-3 text-right">Offers</th>
                    <th class="px-4 py-3 text-right">Confirmed Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 tabular-nums">
                @foreach($clients as $client)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-[#D3003B]">{{ $client->client_code }}</td>
                        <td class="px-4 py-3 font-semibold">
                            <a href="{{ route('clients.show', $client) }}" class="text-slate-900 dark:text-white hover:text-[#D3003B]">{{ $client->company_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $client->industry }}</td>
                        <td class="px-4 py-3 text-right">{{ $client->job_requirements_count }}</td>
                        <td class="px-4 py-3 text-right">{{ $client->client_submissions_count }}</td>
                        <td class="px-4 py-3 text-right">{{ $client->interview_records_count }}</td>
                        <td class="px-4 py-3 text-right">{{ $client->offers_count }}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">{{ $client->joined_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
