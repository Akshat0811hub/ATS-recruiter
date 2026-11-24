<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StorageManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function __construct(private readonly StorageManagementService $storageService) {}

    public function index(Request $request)
    {
        $query = Client::with(['accountManager', 'assignedTeam'])
            ->withCount([
                'jobRequirements',
                'jobRequirements as active_requirements_count' => fn ($q) => $q->whereNotIn('status', ['successfully_closed', 'cancelled']),
                'clientSubmissions',
                'clientSubmissions as shortlisted_count' => fn ($q) => $q->whereIn('client_decision', ['shortlisted', 'interview_requested']),
                'interviewRecords',
                'offers',
                'joiningRecords as joined_count' => fn ($q) => $q->whereIn('status', ['joined', 'placement_completed']),
            ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('client_code', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%")
                    ->orWhere('primary_hr_name', 'like', "%{$search}%");
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $clients = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $managers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['super_admin', 'manager']))->get();
        $teams = Team::where('is_active', true)->get();

        return view('clients.index', compact('clients', 'managers', 'teams'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'locations_csv' => ['nullable', 'string'],
            'primary_hr_name' => ['required', 'string', 'max:150'],
            'primary_hr_designation' => ['nullable', 'string', 'max:120'],
            'primary_hr_phone' => ['required', 'string', 'max:40'],
            'primary_hr_email' => ['required', 'email', 'max:180'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'assigned_team_id' => ['nullable', 'exists:teams,id'],
            'status' => ['required', 'in:active,onboarding,on_hold,inactive'],
            'placement_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'flat_placement_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'replacement_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'recruitment_terms' => ['nullable', 'string'],
            'replacement_policy' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'agreement_file' => ['nullable', 'file', 'max:10240'],
            'create_portal_user' => ['nullable', 'boolean'],
        ]);

        $agreementStorageId = null;
        if ($request->hasFile('agreement_file')) {
            $storageRecord = $this->storageService->storePrivateFile(
                $request->file('agreement_file'),
                'agreement',
                Client::class
            );
            $agreementStorageId = $storageRecord->id;
        }

        $locations = ! empty($data['locations_csv'])
            ? array_values(array_filter(array_map('trim', explode(',', $data['locations_csv']))))
            : [$data['city'] ?? 'Mumbai'];

        $client = Client::create([
            'company_name' => $data['company_name'],
            'industry' => $data['industry'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'],
            'locations' => $locations,
            'primary_hr_name' => $data['primary_hr_name'],
            'primary_hr_designation' => $data['primary_hr_designation'] ?? 'HR Representative',
            'primary_hr_phone' => $data['primary_hr_phone'],
            'primary_hr_email' => strtolower(trim($data['primary_hr_email'])),
            'account_manager_id' => $data['account_manager_id'] ?? Auth::id(),
            'assigned_team_id' => $data['assigned_team_id'] ?? null,
            'status' => $data['status'],
            'agreement_storage_id' => $agreementStorageId,
            'recruitment_terms' => $data['recruitment_terms'] ?? null,
            'placement_fee_percentage' => $data['placement_fee_percentage'],
            'flat_placement_fee' => $data['flat_placement_fee'] ?? null,
            'currency' => $data['currency'],
            'replacement_period_days' => $data['replacement_period_days'],
            'payment_terms_days' => $data['payment_terms_days'],
            'replacement_policy' => $data['replacement_policy'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $portalUserId = null;
        if ($request->boolean('create_portal_user')) {
            $clientRole = Role::where('slug', 'client')->first();
            $existingUser = User::where('email', $client->primary_hr_email)->first();
            if (! $existingUser && $clientRole) {
                $portalUser = User::create([
                    'employee_code' => 'CLT-'.Str::upper(Str::random(5)),
                    'role_id' => $clientRole->id,
                    'client_id' => $client->id,
                    'name' => $client->primary_hr_name,
                    'email' => $client->primary_hr_email,
                    'phone' => $client->primary_hr_phone,
                    'designation' => $client->primary_hr_designation,
                    'password' => Hash::make('OrbiTouch@2026!'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
                $portalUserId = $portalUser->id;
            }
        }

        ClientContact::create([
            'client_id' => $client->id,
            'user_id' => $portalUserId,
            'name' => $client->primary_hr_name,
            'designation' => $client->primary_hr_designation,
            'phone' => $client->primary_hr_phone,
            'email' => $client->primary_hr_email,
            'is_primary' => true,
            'can_access_portal' => true,
        ]);

        AuditService::log('client.created', $client, $client->id, null, [
            'client_code' => $client->client_code,
            'company_name' => $client->company_name,
        ]);

        return redirect()->route('clients.show', $client)->with('success', "Client [{$client->company_name}] ({$client->client_code}) created.");
    }

    public function show(Client $client)
    {
        $client->load([
            'accountManager',
            'assignedTeam',
            'agreementStorage',
            'contacts',
            'jobRequirements.assignments.recruiter',
            'communications.user',
        ]);

        $kpis = [
            'total_requirements' => $client->jobRequirements()->count(),
            'active_vacancies' => (int) $client->jobRequirements()->whereNotIn('status', ['successfully_closed', 'cancelled'])->sum('remaining_count'),
            'candidates_submitted' => $client->clientSubmissions()->count(),
            'candidates_shortlisted' => $client->clientSubmissions()->whereIn('client_decision', ['shortlisted', 'interview_requested'])->count(),
            'interviews_scheduled' => $client->interviewRecords()->count(),
            'candidates_selected' => $client->candidateApplications()->whereIn('pipeline_stage', ['selected', 'offer_released', 'offer_accepted', 'joining_pending', 'joined'])->count(),
            'offers_released' => $client->offers()->count(),
            'candidates_joined' => $client->joiningRecords()->whereIn('status', ['joined', 'placement_completed'])->count(),
            'pending_feedback' => $client->clientSubmissions()->where('client_decision', 'pending_review')->count(),
            'closed_requirements' => $client->jobRequirements()->where('status', 'successfully_closed')->count(),
        ];

        $managers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['super_admin', 'manager']))->get();
        $teams = Team::where('is_active', true)->get();

        return view('clients.show', compact('client', 'kpis', 'managers', 'teams'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'primary_hr_name' => ['required', 'string', 'max:150'],
            'primary_hr_designation' => ['nullable', 'string', 'max:120'],
            'primary_hr_phone' => ['required', 'string', 'max:40'],
            'primary_hr_email' => ['required', 'email', 'max:180'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'assigned_team_id' => ['nullable', 'exists:teams,id'],
            'status' => ['required', 'in:active,onboarding,on_hold,inactive'],
            'placement_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'flat_placement_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'replacement_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'recruitment_terms' => ['nullable', 'string'],
            'replacement_policy' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'agreement_file' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('agreement_file')) {
            $storageRecord = $this->storageService->storePrivateFile(
                $request->file('agreement_file'),
                'agreement',
                Client::class,
                $client->id
            );
            $data['agreement_storage_id'] = $storageRecord->id;
        }
        unset($data['agreement_file']);

        $old = $client->only(['company_name', 'status', 'placement_fee_percentage', 'replacement_period_days']);
        $client->update($data);

        AuditService::log('client.updated', $client, $client->id, $old, $data);

        return back()->with('success', "Client profile [{$client->company_name}] updated.");
    }

    public function storeContact(Request $request, Client $client)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'designation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:180'],
            'department' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
            'create_portal_login' => ['nullable', 'boolean'],
        ]);

        $portalUserId = null;
        if ($request->boolean('create_portal_login')) {
            $clientRole = Role::where('slug', 'client')->first();
            $existingUser = User::where('email', strtolower(trim($data['email'])))->first();
            if (! $existingUser && $clientRole) {
                $portalUser = User::create([
                    'employee_code' => 'CLT-'.Str::upper(Str::random(5)),
                    'role_id' => $clientRole->id,
                    'client_id' => $client->id,
                    'name' => $data['name'],
                    'email' => strtolower(trim($data['email'])),
                    'phone' => $data['phone'] ?? null,
                    'designation' => $data['designation'] ?? 'HR Representative',
                    'password' => Hash::make('OrbiTouch@2026!'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
                $portalUserId = $portalUser->id;
            }
        }

        $contact = ClientContact::create([
            'client_id' => $client->id,
            'user_id' => $portalUserId,
            'name' => $data['name'],
            'designation' => $data['designation'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => strtolower(trim($data['email'])),
            'department' => $data['department'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'can_access_portal' => true,
        ]);

        AuditService::log('client_contact.created', $contact, $contact->id, null, $contact->toArray());

        return back()->with('success', "HR contact [{$contact->name}] added to {$client->company_name}.");
    }
}
