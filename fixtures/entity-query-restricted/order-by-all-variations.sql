SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
JOIN (
                SELECT ref_apie_resource__test_order_id AS entity_id, SUM(idf * tf) AS accuracy
                FROM apie_index_table
                WHERE text = "i" OR text = "searched" OR text LIKE "%this%"
                GROUP BY entity_id
            ) subquery ON entity.id = subquery.entity_id
JOIN apie_access_control_list acl ON (entity.id = acl.ref_apie_resource__test_order_id)
WHERE (1)
AND (1)
AND (acl.permission IN (""))
GROUP BY entity.id
ORDER BY MAX(subquery.accuracy) DESC, entity.created_at ASC, entity.id ASC
 LIMIT 20