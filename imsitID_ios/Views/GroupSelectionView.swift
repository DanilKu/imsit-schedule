//
//  GroupSelectionView.swift
//  imsitID
//

import SwiftUI

struct GroupSelectionView: View {
    @EnvironmentObject var appState: AppState
    @Binding var isPresented: Bool
    @State private var searchText = ""
    @State private var favoriteGroups: [String] = []
    
    var filteredGroups: [String] {
        let groups = appState.availableGroups
        if searchText.isEmpty {
            return sortedGroups(groups)
        }
        return sortedGroups(groups.filter { group in
            group.localizedCaseInsensitiveContains(searchText)
        })
    }
    
    func sortedGroups(_ groups: [String]) -> [String] {
        let favorites = StorageService.shared.getFavoriteGroups()
        return groups.sorted { group1, group2 in
            let isFav1 = favorites.contains(group1)
            let isFav2 = favorites.contains(group2)
            if isFav1 && !isFav2 { return true }
            if !isFav1 && isFav2 { return false }
            if isFav1 && isFav2 {
                let idx1 = favorites.firstIndex(of: group1) ?? Int.max
                let idx2 = favorites.firstIndex(of: group2) ?? Int.max
                return idx1 < idx2
            }
            return group1 < group2
        }
    }
    
    var body: some View {
        NavigationView {
            ZStack {
                LinearGradient(
                    colors: [
                        Color(red: 0.043, green: 0.071, blue: 0.125),
                        Color(red: 0.208, green: 0.208, blue: 0.208)
                    ],
                    startPoint: .top,
                    endPoint: .bottom
                )
                .ignoresSafeArea()
                
                VStack(spacing: 0) {
                    // Search bar
                    HStack {
                        HStack {
                            Image(systemName: "magnifyingglass")
                                .foregroundColor(.white.opacity(0.6))
                            TextField("Поиск группы...", text: $searchText)
                                .foregroundColor(.white)
                        }
                        .padding(.horizontal, 12)
                        .padding(.vertical, 10)
                        .glassEffect(cornerRadius: 12)
                        
                        Button(action: {
                            isPresented = false
                            DispatchQueue.main.asyncAfter(deadline: .now() + 0.3) {
                                // Show teacher selection
                            }
                        }) {
                            Text("Препод")
                                .font(.system(size: 14, weight: .medium))
                                .foregroundColor(.white)
                                .padding(.horizontal, 12)
                                .padding(.vertical, 10)
                                .glassEffect(cornerRadius: 12)
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                    
                    // Groups list
                    ScrollView {
                        LazyVStack(spacing: 12) {
                            ForEach(filteredGroups, id: \.self) { group in
                                GroupRow(group: group, isPresented: $isPresented)
                            }
                        }
                        .padding(.horizontal, 16)
                        .padding(.bottom, 20)
                    }
                }
            }
            .navigationTitle("Выберите группу")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Закрыть") {
                        isPresented = false
                    }
                    .foregroundColor(.white)
                }
            }
        }
        .onAppear {
            favoriteGroups = StorageService.shared.getFavoriteGroups()
        }
    }
}

struct GroupRow: View {
    let group: String
    @Binding var isPresented: Bool
    @EnvironmentObject var appState: AppState
    @State private var isFavorite: Bool
    
    init(group: String, isPresented: Binding<Bool>) {
        self.group = group
        self._isPresented = isPresented
        _isFavorite = State(initialValue: StorageService.shared.isFavoriteGroup(group))
    }
    
    var body: some View {
        Button(action: {
            appState.selectGroup(group)
            isPresented = false
        }) {
            HStack(spacing: 12) {
                Image(systemName: "person.3.fill")
                    .font(.system(size: 16))
                    .foregroundColor(Color(red: 0.376, green: 0.510, blue: 0.980))
                    .frame(width: 32, height: 32)
                    .background(Color(red: 0.231, green: 0.510, blue: 0.965).opacity(0.2))
                    .cornerRadius(10)
                
                VStack(alignment: .leading, spacing: 2) {
                    Text(group)
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.white)
                    Text(group)
                        .font(.system(size: 13))
                        .foregroundColor(.white.opacity(0.7))
                }
                
                Spacer()
                
                Button(action: {
                    isFavorite.toggle()
                    if isFavorite {
                        StorageService.shared.addFavoriteGroup(group)
                    } else {
                        StorageService.shared.removeFavoriteGroup(group)
                    }
                }) {
                    Image(systemName: isFavorite ? "star.fill" : "star")
                        .font(.system(size: 16))
                        .foregroundColor(isFavorite ? Color(red: 1.0, green: 0.820, blue: 0.376) : .white.opacity(0.35))
                }
            }
            .padding(12)
            .glassEffect(cornerRadius: 12)
        }
        .buttonStyle(PlainButtonStyle())
    }
}

