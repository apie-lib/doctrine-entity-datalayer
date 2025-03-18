SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
WHERE (1)
GROUP BY entity.id
ORDER BY entity.apie_name DESC
 LIMIT 20