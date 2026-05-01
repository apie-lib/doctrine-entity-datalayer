SELECT DISTINCT entity.*
            FROM apie_resource__test_order entity
JOIN apie_access_control_list acl ON (entity.id = acl.ref_apie_resource__test_order_id)
WHERE (1)
AND (acl.permission IN (""))
GROUP BY entity.id
ORDER BY entity.created_at ASC, entity.id ASC
 LIMIT 20