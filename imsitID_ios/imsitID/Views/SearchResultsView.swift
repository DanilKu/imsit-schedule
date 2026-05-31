//
//  SearchResultsView.swift
//  imsitID
//

import SwiftUI

struct SearchResultsView: View {
    @EnvironmentObject var appState: AppState
    @Binding var searchText: String
    @Binding var isPresented: Bool
    
    var filteredGroups: [String] {
        guard !searchText.isEmpty else { return [] }
        let query = searchText.lowercased()
        return appState.availableGroups.filter { group in
            group.lowercased().contains(query)
        }
    }
    
    var filteredTeachers: [String] {
        guard !searchText.isEmpty else { return [] }
        let query = searchText.lowercased()
        return appState.availableTeachers.filter { teacher in
            teacher.lowercased().contains(query)
        }
    }
    
    var body: some View {
        if !searchText.isEmpty && (!filteredGroups.isEmpty || !filteredTeachers.isEmpty) {
            VStack(spacing: 0) {
                ScrollView {
                    VStack(spacing: 12) {
                        // Groups
                        if !filteredGroups.isEmpty {
                            ForEach(filteredGroups.prefix(10), id: \.self) { group in
                                SearchResultRow(
                                    title: group,
                                    subtitle: "Группа",
                                    icon: "person.3.fill",
                                    iconColor: Color(red: 0.376, green: 0.510, blue: 0.980)
                                ) {
                                    appState.selectGroup(group)
                                    isPresented = false
                                    searchText = ""
                                }
                            }
                        }
                        
                        // Teachers
                        if !filteredTeachers.isEmpty {
                            ForEach(filteredTeachers.prefix(10), id: \.self) { teacher in
                                SearchResultRow(
                                    title: teacher,
                                    subtitle: "Преподаватель",
                                    icon: "person.fill",
                                    iconColor: Color(red: 0.659, green: 0.706, blue: 0.996)
                                ) {
                                    appState.selectTeacher(teacher)
                                    isPresented = false
                                    searchText = ""
                                }
                            }
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
            }
            .frame(maxHeight: 400)
            .glassEffect(cornerRadius: 16)
            .padding(.horizontal, 16)
            .padding(.top, 8)
        }
    }
}

struct SearchResultRow: View {
    let title: String
    let subtitle: String
    let icon: String
    let iconColor: Color
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 16))
                    .foregroundColor(iconColor)
                    .frame(width: 32, height: 32)
                    .background(iconColor.opacity(0.2))
                    .cornerRadius(10)
                
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.white)
                    Text(subtitle)
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                }
                
                Spacer()
            }
            .padding(12)
            .glassEffect(cornerRadius: 12)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

