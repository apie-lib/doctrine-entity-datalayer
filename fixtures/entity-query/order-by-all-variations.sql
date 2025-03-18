SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
JOIN (
                SELECT ref_apie_resource__test_order_id AS entity_id, SUM(idf * tf) AS accuracy
                FROM apie_index_table
                WHERE text = "i" OR text = "searched" OR text LIKE "%this%"
                GROUP BY entity_id
            ) subquery ON entity.id = subquery.entity_id
WHERE (entity.id IN (SELECT parent_id as id FROM apie_name WHERE value LIKE "%Exact match%"))
AND (1)
AND (1)
AND (entity.`requires_update` IS NOT NULL AND entity.`requires_update` <= CURRENT_TIMESTAMP())
AND (1)
GROUP BY entity.id
ORDER BY entity.apie_name ASC, entity.apie_value DESC, entity.`requires_update` ASC, MAX(subquery.accuracy) DESC
 LIMIT 20