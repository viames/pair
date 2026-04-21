-- Widen remember-me tokens so applications can store longer opaque tokens.
ALTER TABLE `user_remembers`
  MODIFY `remember_me` varchar(128) NOT NULL;
