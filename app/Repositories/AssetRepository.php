<?php
namespace App\Repositories;

use App\Helpers\Database;

final class AssetRepository
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO assets
             (proyecto_id,tipo,filename,original_filename,ruta,mime_type,tamano_bytes,width,height,alt_text,title,uploaded_by)
             VALUES
             (:proyecto_id,:tipo,:filename,:original_filename,:ruta,:mime_type,:tamano_bytes,:width,:height,:alt_text,:title,:uploaded_by)'
        );
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }
}
