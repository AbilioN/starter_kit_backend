<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Assistant;
use App\Domain\Repositories\AssistantRepositoryInterface;
use App\Models\Assistant as AssistantModel;

class AssistantRepository implements AssistantRepositoryInterface
{
    public function findById(int $id): ?Assistant
    {
        $model = AssistantModel::find($id);
        
        if (!$model) {
            return null;
        }
        
        return new Assistant(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            avatar: $model->avatar,
            capabilities: $model->capabilities ?? [],
            isActive: $model->is_active
        );
    }
    
    public function findActiveById(int $id): ?Assistant
    {
        $model = AssistantModel::where('id', $id)
            ->where('is_active', true)
            ->first();
            
        if (!$model) {
            return null;
        }
        
        return new Assistant(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            avatar: $model->avatar,
            capabilities: $model->capabilities ?? [],
            isActive: $model->is_active
        );
    }
    
    public function getAllActive(): array
    {
        return AssistantModel::where('is_active', true)
            ->get()
            ->map(function ($model) {
                return new Assistant(
                    id: $model->id,
                    name: $model->name,
                    description: $model->description,
                    avatar: $model->avatar,
                    capabilities: $model->capabilities ?? [],
                    isActive: $model->is_active
                );
            })
            ->toArray();
    }
}
