SELECT DISTINCT entity.*
            FROM apie_entity_test_order entity
WHERE (entity.apie_name = "%You get 60\\% discount%")
ORDER BY entity.id ASC
LIMIT 0, 20