<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Models\UserAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetRequestController extends Controller
{
    /**
     * Lista todas as solicitações de reset (para admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = PasswordResetRequest::with(['user:id,name,email', 'handler:id,name']);

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Ordenação
        $sortBy = $request->input('sortBy', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');
        $query->orderBy($sortBy, $orderBy);

        // Paginação
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage == -1) {
            $requests = $query->get();
            $total = $requests->count();
        } else {
            $paginated = $query->paginate($perPage);
            $requests = $paginated->items();
            $total = $paginated->total();
        }

        return response()->json([
            'requests' => $requests,
            'totalRequests' => $total,
        ]);
    }

    /**
     * Estatísticas das solicitações
     */
    public function statistics(): JsonResponse
    {
        $pending = PasswordResetRequest::where('status', PasswordResetRequest::STATUS_PENDING)->count();
        $approved = PasswordResetRequest::where('status', PasswordResetRequest::STATUS_APPROVED)->count();
        $rejected = PasswordResetRequest::where('status', PasswordResetRequest::STATUS_REJECTED)->count();
        $total = PasswordResetRequest::count();

        // Solicitações pendentes mais antigas
        $oldestPending = PasswordResetRequest::where('status', PasswordResetRequest::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->with('user:id,name,email')
            ->limit(5)
            ->get();

        return response()->json([
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'total' => $total,
            'oldestPending' => $oldestPending,
        ]);
    }

    /**
     * Usuário solicita reset de senha (não autenticado)
     */
    public function requestReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.exists' => 'Usuário não encontrado.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Verificar se já existe uma solicitação pendente
        $existingRequest = PasswordResetRequest::where('user_id', $user->id)
            ->where('status', PasswordResetRequest::STATUS_PENDING)
            ->first();

        if ($existingRequest) {
            return response()->json([
                'message' => 'Já existe uma solicitação pendente para este usuário.',
                'request' => $existingRequest,
            ], 409);
        }

        $resetRequest = PasswordResetRequest::createRequest(
            $user->id,
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Solicitação de reset enviada com sucesso. Aguarde aprovação do administrador.',
            'request' => $resetRequest,
        ], 201);
    }

    /**
     * Admin aprova a solicitação
     */
    public function approve(Request $request, PasswordResetRequest $passwordResetRequest): JsonResponse
    {
        if ($passwordResetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
            return response()->json([
                'message' => 'Esta solicitação já foi processada.',
            ], 409);
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $passwordResetRequest->approve(
            auth()->id(),
            $validated['admin_notes'] ?? null
        );

        // Gerar senha temporária
        $temporaryPassword = Str::random(12);
        $user = $passwordResetRequest->user;

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'password_reset_required' => true,
            'password_changed_at' => now(),
        ]);

        // Registrar auditoria
        UserAuditLog::log(
            $user->id,
            UserAuditLog::ACTION_PASSWORD_RESET,
            auth()->id(),
            'password',
            null,
            null,
            'Solicitação de reset aprovada'
        );

        return response()->json([
            'message' => 'Solicitação aprovada com sucesso.',
            'temporaryPassword' => $temporaryPassword,
            'request' => $passwordResetRequest->fresh(),
        ]);
    }

    /**
     * Admin rejeita a solicitação
     */
    public function reject(Request $request, PasswordResetRequest $passwordResetRequest): JsonResponse
    {
        if ($passwordResetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
            return response()->json([
                'message' => 'Esta solicitação já foi processada.',
            ], 409);
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:500'],
        ], [
            'admin_notes.required' => 'É necessário informar o motivo da rejeição.',
        ]);

        $passwordResetRequest->reject(
            auth()->id(),
            $validated['admin_notes']
        );

        return response()->json([
            'message' => 'Solicitação rejeitada.',
            'request' => $passwordResetRequest->fresh(),
        ]);
    }
}
