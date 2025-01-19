SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity

GROUP BY entity.id
ORDER BY entity.created_at DESC
 LIMIT 20