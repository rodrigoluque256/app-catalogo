<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\User\Actions\CreateUserAction;
use App\Application\User\Actions\DeleteUserAction;
use App\Application\User\Actions\ListUsersAction;
use App\Application\User\Actions\ResetUserPasswordAction;
use App\Application\User\Actions\UpdateUserAction;
use App\Models\User;
use App\UI\Http\Requests\User\CreateUserRequest;
use App\UI\Http\Requests\User\UpdateUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class UserController
{
    public function index(Request $request, ListUsersAction $action): JsonResponse
    {
        $actorId = $this->actorId($request);

        return response()->json(['data' => $action->execute()
            ->map(fn (array $row): array => $this->present($row['user'], $row['role'], $actorId))
            ->values()]);
    }

    public function store(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('name')->trim()->toString(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
        );

        return response()->json(['data' => [
            ...$this->present($result['user'], $result['role'], $this->actorId($request)),
            // Se muestra una sola vez: no se puede volver a consultar.
            'temporary_password' => $result['temporary_password'],
        ]], Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, string $user, UpdateUserAction $action): JsonResponse
    {
        $actorId = $this->actorId($request);
        $result = $action->execute(
            $actorId,
            $user,
            $request->string('name')->trim()->toString(),
            $request->string('role')->toString(),
        );

        return response()->json(['data' => $this->present($result['user'], $result['role'], $actorId)]);
    }

    public function destroy(Request $request, string $user, DeleteUserAction $action): Response
    {
        $action->execute($this->actorId($request), $user);

        return response()->noContent();
    }

    public function resetPassword(string $user, ResetUserPasswordAction $action): JsonResponse
    {
        return response()->json(['data' => ['temporary_password' => $action->execute($user)]]);
    }

    private function actorId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, ?string $role, int $actorId): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'must_change_password' => $user->must_change_password,
            'is_current_user' => $user->id === $actorId,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
