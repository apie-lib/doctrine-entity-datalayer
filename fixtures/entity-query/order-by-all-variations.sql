SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
JOIN (
                SELECT ref_apie_resource__test_order_id AS entity_id, SUM(idf * tf) AS accuracy
                FROM apie_index_table
                WHERE text LIKE "%i%" OR text LIKE "%searched%" OR text LIKE "%this%"
                GROUP BY entity_id
            ) subquery ON entity.id = subquery.entity_id
WHERE (1)
AND (entity.id IN (SELECT parent_id as id FROM apie_name WHERE value LIKE "%Exact match%"))
AND (1)
AND (1)
AND (entity.requires_update IS NOT NULL AND entity.requires_update <= CURRENT_TIMESTAMP())
GROUP BY entity.id
ORDER BY MAX(subquery.accuracy) DESC, entity.apie_name ASC, entity.apie_value DESC, entity.requires_update ASC
 LIMIT 20