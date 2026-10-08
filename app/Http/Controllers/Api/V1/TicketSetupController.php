<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Actions\Setup\DeleteCustomStatus;
use App\Domain\Tickets\Actions\Setup\SaveCustomStatus;
use App\Domain\Tickets\Actions\Setup\SaveTicketCategory;
use App\Domain\Tickets\Actions\Setup\SaveTicketField;
use App\Domain\Tickets\Actions\Setup\SaveTicketForm;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Http\Requests\Admin\SaveCustomStatusRequest;
use App\Http\Requests\Admin\SaveTicketCategoryRequest;
use App\Http\Requests\Admin\SaveTicketFieldRequest;
use App\Http\Requests\Admin\SaveTicketFormRequest;
use App\Http\Resources\Api\V1\CustomStatusResource;
use App\Http\Resources\Api\V1\TicketCategoryResource;
use App\Http\Resources\Api\V1\TicketFieldResource;
use App\Http\Resources\Api\V1\TicketFormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Changes to ticket setup, with the same rules as the admin center. Updates take the full
 * object, as when creating. List these with the `GET` endpoints of the same paths.
 *
 * @tags Ticket setup
 */
class TicketSetupController extends ApiController
{
    /**
     * Create a status.
     */
    public function storeStatus(SaveCustomStatusRequest $request, SaveCustomStatus $saveStatus): JsonResponse
    {
        return (new CustomStatusResource($saveStatus->create($request->validated())))->response()->setStatusCode(201);
    }

    /**
     * Update a status.
     */
    public function updateStatus(SaveCustomStatusRequest $request, CustomStatus $ticketStatus, SaveCustomStatus $saveStatus): CustomStatusResource
    {
        return new CustomStatusResource($saveStatus->update($ticketStatus, $request->validated()));
    }

    /**
     * Delete a status.
     *
     * Its tickets move to the default status of the same category, which can't be deleted.
     */
    public function destroyStatus(CustomStatus $ticketStatus, DeleteCustomStatus $deleteStatus): Response
    {
        $deleteStatus->handle($ticketStatus);

        return response()->noContent();
    }

    /**
     * Create a category.
     */
    public function storeCategory(SaveTicketCategoryRequest $request, SaveTicketCategory $saveCategory): JsonResponse
    {
        return (new TicketCategoryResource($saveCategory->create($request->categoryAttributes())))->response()->setStatusCode(201);
    }

    /**
     * Update a category.
     */
    public function updateCategory(SaveTicketCategoryRequest $request, TicketCategory $ticketCategory, SaveTicketCategory $saveCategory): TicketCategoryResource
    {
        return new TicketCategoryResource($saveCategory->update($ticketCategory, $request->categoryAttributes()));
    }

    /**
     * Delete a category.
     *
     * Its tickets keep their field values but no longer have a category.
     */
    public function destroyCategory(TicketCategory $ticketCategory): Response
    {
        $ticketCategory->delete();

        return response()->noContent();
    }

    /**
     * Create a custom field.
     */
    public function storeField(SaveTicketFieldRequest $request, SaveTicketField $saveField): JsonResponse
    {
        return (new TicketFieldResource($saveField->create($request->validated())))->response()->setStatusCode(201);
    }

    /**
     * Update a custom field.
     */
    public function updateField(SaveTicketFieldRequest $request, TicketField $ticketField, SaveTicketField $saveField): TicketFieldResource
    {
        return new TicketFieldResource($saveField->update($ticketField, $request->validated()));
    }

    /**
     * Delete a custom field.
     *
     * Values already stored on tickets are kept.
     */
    public function destroyField(TicketField $ticketField): Response
    {
        $ticketField->delete();

        return response()->noContent();
    }

    /**
     * Create a ticket form.
     *
     * `fields` lists the form's fields in order: `[{id, is_required}]`.
     */
    public function storeForm(SaveTicketFormRequest $request, SaveTicketForm $saveForm): JsonResponse
    {
        return (new TicketFormResource($saveForm->create($request->formAttributes(), $request->fields())))->response()->setStatusCode(201);
    }

    /**
     * Update a ticket form.
     */
    public function updateForm(SaveTicketFormRequest $request, TicketForm $ticketForm, SaveTicketForm $saveForm): TicketFormResource
    {
        return new TicketFormResource($saveForm->update($ticketForm, $request->formAttributes(), $request->fields())->load('fields'));
    }

    /**
     * Delete a ticket form.
     *
     * Categories that used it fall back to the default form.
     */
    public function destroyForm(TicketForm $ticketForm): Response
    {
        $ticketForm->delete();

        return response()->noContent();
    }
}
