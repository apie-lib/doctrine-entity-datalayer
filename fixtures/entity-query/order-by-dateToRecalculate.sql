SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
WHERE (entity.requires_update IS NOT NULL AND entity.requires_update <= CURRENT_TIMESTAMP())
GROUP BY entity.id
ORDER BY entity.requires_update DESC
 LIMIT 20