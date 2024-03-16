SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
WHERE (entity.id IN (SELECT parent_id as id FROM apie_name WHERE value LIKE "%Exact match%"))
GROUP BY entity.id
ORDER BY entity.id ASC
LIMIT 0, 20