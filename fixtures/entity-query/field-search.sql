SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
WHERE (entity.apie_name LIKE "%Exact match%")
ORDER BY entity.id ASC
LIMIT 0, 20