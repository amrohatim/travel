# Phone Auth Data Audit

Run this query before rollout to identify accounts that cannot log in with phone-only auth:

```sql
SELECT id, name, role, phone
FROM users
WHERE phone IS NULL
   OR phone NOT REGEXP '^[0-9]{10}$';
```

These users must be corrected to a unique 10-digit phone number before they can authenticate.
