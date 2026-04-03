-- Rename the remember-me table to the singular Pair naming convention.
RENAME TABLE `users_remembers` TO `user_remembers`;

-- Rename the passkeys table to the singular Pair naming convention.
RENAME TABLE `users_passkeys` TO `user_passkeys`;
