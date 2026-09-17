-- Secure-by-default account creation. Existing accounts keep their current value.
ALTER TABLE `tbl_users`
    MODIFY `must_change_password` TINYINT(1) NOT NULL DEFAULT 1;
