SELECT DISTINCT entity.*
            FROM apie_entity_test_order entity
JOIN (
                SELECT entity_id, SUM(idf * tf) AS accuracy
                FROM apie_index_test_order
                WHERE text LIKE "%i%" OR text LIKE "%searched%" OR text LIKE "%this%"
                GROUP BY entity_id
            ) subquery ON entity.id = subquery.entity_id
WHERE (1)
ORDER BY subquery.accuracy DESC
LIMIT 0, 20