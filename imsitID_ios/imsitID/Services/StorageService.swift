//
//  StorageService.swift
//  imsitID
//

import Foundation

final class StorageService: @unchecked Sendable {
    static let shared = StorageService()
    
    private let userDefaults = UserDefaults.standard
    private let scheduleKey = "cached_schedule"
    private let selectedGroupKey = "selected_group"
    private let selectedTeacherKey = "selected_teacher"
    private let viewModeKey = "view_mode"
    private let favoritesGroupsKey = "favorites_groups"
    private let favoritesTeachersKey = "favorites_teachers"
    
    private init() {}
    
    // MARK: - Schedule Storage
    
    nonisolated func saveSchedule(_ schedule: Schedule, for group: String? = nil, teacher: String? = nil) {
        guard group != nil || teacher != nil else { return }
        
        do {
            let encoder = JSONEncoder()
            let data = try encoder.encode(schedule)
            
            let key: String
            if let group = group {
                key = "\(scheduleKey)_group_\(group)"
            } else if let teacher = teacher {
                key = "\(scheduleKey)_teacher_\(teacher)"
            } else {
                return
            }
            
            userDefaults.set(data, forKey: key)
            
            // Сохраняем метаданные
            if let group = group {
                userDefaults.set(group, forKey: selectedGroupKey)
                userDefaults.set("group", forKey: viewModeKey)
            } else if let teacher = teacher {
                userDefaults.set(teacher, forKey: selectedTeacherKey)
                userDefaults.set("teacher", forKey: viewModeKey)
            }
        } catch {
            print("Error saving schedule: \(error)")
        }
    }
    
    nonisolated func loadSchedule(for group: String? = nil, teacher: String? = nil) -> Schedule? {
        guard group != nil || teacher != nil else { return nil }
        
        let key: String
        if let group = group {
            key = "\(scheduleKey)_group_\(group)"
        } else if let teacher = teacher {
            key = "\(scheduleKey)_teacher_\(teacher)"
        } else {
            return nil
        }
        
        guard let data = userDefaults.data(forKey: key) else {
            return nil
        }
        
        do {
            let decoder = JSONDecoder()
            return try decoder.decode(Schedule.self, from: data)
        } catch {
            print("Error loading schedule: \(error)")
            return nil
        }
    }
    
    // MARK: - Selection Storage
    
    nonisolated func saveSelectedGroup(_ group: String) {
        userDefaults.set(group, forKey: selectedGroupKey)
        userDefaults.set("group", forKey: viewModeKey)
        userDefaults.removeObject(forKey: selectedTeacherKey)
    }
    
    nonisolated func saveSelectedTeacher(_ teacher: String) {
        userDefaults.set(teacher, forKey: selectedTeacherKey)
        userDefaults.set("teacher", forKey: viewModeKey)
        userDefaults.removeObject(forKey: selectedGroupKey)
    }
    
    nonisolated func getSelectedGroup() -> String? {
        return userDefaults.string(forKey: selectedGroupKey)
    }
    
    nonisolated func getSelectedTeacher() -> String? {
        return userDefaults.string(forKey: selectedTeacherKey)
    }
    
    nonisolated func getViewMode() -> String {
        return userDefaults.string(forKey: viewModeKey) ?? "group"
    }
    
    // MARK: - Favorites
    
    nonisolated func getFavoriteGroups() -> [String] {
        return userDefaults.stringArray(forKey: favoritesGroupsKey) ?? []
    }
    
    nonisolated func getFavoriteTeachers() -> [String] {
        return userDefaults.stringArray(forKey: favoritesTeachersKey) ?? []
    }
    
    nonisolated func addFavoriteGroup(_ group: String) {
        var favorites = getFavoriteGroups()
        if !favorites.contains(group) {
            favorites.insert(group, at: 0)
            userDefaults.set(favorites, forKey: favoritesGroupsKey)
        }
    }
    
    nonisolated func addFavoriteTeacher(_ teacher: String) {
        var favorites = getFavoriteTeachers()
        if !favorites.contains(teacher) {
            favorites.insert(teacher, at: 0)
            userDefaults.set(favorites, forKey: favoritesTeachersKey)
        }
    }
    
    nonisolated func removeFavoriteGroup(_ group: String) {
        var favorites = getFavoriteGroups()
        favorites.removeAll { $0 == group }
        userDefaults.set(favorites, forKey: favoritesGroupsKey)
    }
    
    nonisolated func removeFavoriteTeacher(_ teacher: String) {
        var favorites = getFavoriteTeachers()
        favorites.removeAll { $0 == teacher }
        userDefaults.set(favorites, forKey: favoritesTeachersKey)
    }
    
    nonisolated func isFavoriteGroup(_ group: String) -> Bool {
        return getFavoriteGroups().contains(group)
    }
    
    nonisolated func isFavoriteTeacher(_ teacher: String) -> Bool {
        return getFavoriteTeachers().contains(teacher)
    }
}

