<?php

namespace Fico7489\Es;

interface EsInterface
{
    public function getEsIndexPrefix(): string;

    public function getEsIndexName(): string;

    public function getEsData(): array;

    public function getEsDataRelations(): array;

    public function getEsId(): int;

    public function getIsForSync(): bool;

    public function getStoreSoftDeleted(): bool;

    public static function getRelatedUpdate(): array;
}
